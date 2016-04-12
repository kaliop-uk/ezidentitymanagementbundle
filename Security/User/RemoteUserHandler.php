<?php

namespace Kaliop\IdentityManagementBundle\Security\User;

use Kaliop\IdentityManagementBundle\Adapter\ClientInterface;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\User\User;

/**
 * A 'generic' Remote user handler class.
 *
 * For the common cases, you will need to implement only getGroupsFromProfile() and setFieldValuesFromProfile().
 * But you can subclass more methods for more complex scenarios :-)
 *
 * It handles
 * - multiple user groups assignments per user
 * - automatic update of the ez user when its ldap profile has changed compared to the stored one
 */
abstract class RemoteUserHandler
{
    protected $client;
    protected $repository;
    protected $settings;
    protected $tempFiles = array();

    protected $remoteIdPrefix = 'ldap_md5:';

    public function __construct(ClientInterface $client, Repository $repository, array $settings)
    {
        $this->client = $client;
        $this->repository = $repository;
        $this->settings = $settings;
    }

    /**
     * @param RemoteUser $user
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    public function createRepoUser(RemoteUser $user)
    {
        return $this->repository->sudo(
            function() use ($user)
            {
                /// @todo support creating users using a different user account
                //$this->repository->setCurrentUser($userService->loadUser($this->settings['user_creator']));

                $userService = $this->repository->getUserService();
                $profile = $user->getProfile();

                // the user passwords we do not store locally
                $userCreateStruct = $userService->newUserCreateStruct(
                    // is 128 bytes enough for everyone? (pun intended)
                    $user->getUsername(), $user->getEmail(), bin2hex(random_bytes(128)),
                    $this->settings['default_content_language'],
                    $this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->settings['user_contenttype'])
                );

                $this->setFieldValuesFromProfile($profile, $userCreateStruct);

                // store an md5 of the profile, to allow efficient checking of the need for updates
                $userCreateStruct->remoteId = $this->remoteIdPrefix . $this->profileHash($profile);

                /// @todo test/document what happens when we get an empty array...
                $userGroups = $this->getGroupsFromProfile($profile);
                $repoUser = $userService->createUser($userCreateStruct, $userGroups);

                $this->cleanUpAfterUserCreation();

                return $repoUser;
            }
        );
    }

    /**
     * @param RemoteUser $user
     * @param $eZUser (is this an eZ\Publish\API\Repository\Values\User\User ?)
     */
    public function updateRepoUser(RemoteUser $user, $eZUser)
    {
        if ($this->localUserNeedsUpdating($user, $eZUser)) {
            return $this->repository->sudo(
                function() use ($user, $eZUser)
                {
                    $userService = $this->repository->getUserService();
                    $contentService = $this->repository->getContentService();
                    $profile = $user->getProfile();

                    $userUpdateStruct = $userService->newUserUpdateStruct();
                    $contentUpdateStruct = $contentService->newContentUpdateStruct();
                    $this->setFieldValuesFromProfile($profile, $contentUpdateStruct);
                    $userUpdateStruct->contentUpdateStruct = $contentUpdateStruct;

                    // update the stored md5 of the profile, to allow efficient checking of the need for updates in the future
                    $contentMetadataUpdateStruct = $contentService->newContentMetadataUpdateStruct();
                    $contentMetadataUpdateStruct->remoteId = $this->remoteIdPrefix . $this->profileHash($profile);

                    // we use a transaction since there are multiple db operations
                    try {
                        $this->repository->beginTransaction();

                        $repoUser = $userService->updateUser($eZUser, $userUpdateStruct);

                        $content = $contentService->updateContentMetadata($repoUser->contentInfo, $contentMetadataUpdateStruct);

                        // fix user groups assignments: first add new ones, then remove unused current ones (we can not hit 0 groups during the updating :-) )
                        /// @todo test/document what happens when we get an empty array...
                        $newUserGroups = $this->getGroupsFromProfile($profile);
                        $currentUserGroups = $userService->loadUserGroupsOfUser($eZUser);
                        $groupsToRemove = array();
                        foreach($currentUserGroups as $currentUserGroup) {
                            if (!array_key_exists($currentUserGroup->id, $newUserGroups)) {
                                $groupsToRemove[] = $currentUserGroup;
                            } else {
                                unset($newUserGroups[$currentUserGroup->id]);
                            }
                        }
                        foreach ($newUserGroups as $newUserGroup) {
                            $userService->assignUserToUserGroup($repoUser, $newUserGroup);
                        }
                        foreach ($groupsToRemove as $groupToRemove) {
                            $userService->unAssignUserFromUserGroup($repoUser, $groupToRemove);
                        }

                        $this->repository->commit();
                    } catch (\Exception $e) {
                        $this->repository->rollback();
                        $this->cleanUpAfterUserUpdate();
                        throw $e;
                    }

                    $this->cleanUpAfterUserUpdate();
                    return $repoUser;
                }
            );
        }
    }

    protected function getRemoteIdFromProfile($profile)
    {

    }

    /**
     * Load (and possibly create on the fly) all the user groups needed for this user, based on his profile.
     *
     * @param $profile
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup[] indexed by group id
     */
    abstract protected function getGroupsFromProfile($profile);

    /**
     * @param $profile
     * @param \eZ\Publish\API\Repository\Values\Content\ContentCreateStruct $userCreateStruct
     *
     * @todo allow to define simple field mappings in settings
     */
    abstract protected function setFieldValuesFromProfile($profile, $userCreateStruct);

    /**
     * Checks if the local user profile needs updating compared to the remote user profile
     *
     * @param RemoteUser $remoteUser
     * @param $eZUser (is this an eZ\Publish\API\Repository\Values\User\User ?)
     * @return bool
     */
    protected function localUserNeedsUpdating(RemoteUser $remoteUser, $eZUser)
    {
        return $this->profileHash($remoteUser->getProfile()) !== str_replace($this->remoteIdPrefix, '', $eZUser->contentInfo->remoteId);
    }

    /**
     * Generates a unique hash for the user profile
     * @param $profile
     * @return string
     */
    protected function profileHash($profile)
    {
        return md5(var_export($profile, true));
    }

    /**
     * A helper for importing data into image/file fields
     * @param string $data
     * @param string $prefix
     * @return string
     */
    protected function createTempFile($data, $prefix='')
    {
        $imageFileName = trim(tempnam(sys_get_temp_dir(), $prefix), '.');
        file_put_contents($imageFileName, $data);
        $this->tempFiles[] = $imageFileName;

        return $imageFileName;
    }

    /**
     * Needed to clean up after createTempFile()
     */
    protected function cleanUpAfterUserCreation()
    {
        foreach ($this->tempFiles as $fileName) {
            if (is_file( $fileName))
                unlink($fileName);
        }
    }

    /**
     * Needed to clean up after createTempFile()
     */
    protected function cleanUpAfterUserUpdate()
    {
        foreach ($this->tempFiles as $fileName) {
            if (is_file( $fileName))
                unlink($fileName);
        }
    }
}
