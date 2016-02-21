<?php

namespace  Kaliop\IdentityManagementBundle\Adapter\LDAP;

use Kaliop\IdentityManagementBundle\Adapter\ClientInterface;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\User\User;

/**
 * A 'generic' LDAP Remote user handler class.
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

    public function __construct(ClientInterface $client, Repository $repository, array $settings)
    {
        $this->client = $client;
        $this->repository = $repository;
        $this->settings = $settings;
    }

    public function createRepoUser(RemoteUser $user)
    {
        return $this->repository->sudo(
            function() use ($user)
            {
                /// @todo support creating users using a different user account
                //$this->repository->setCurrentUser($userService->loadUser($this->settings['user_creator']));

                $userService = $this->repository->getUserService();
                $contentTypeService = $this->repository->getContentTypeService();
                $profile = $user->getProfile();

                // the user passwords we do not store locally
                $userCreateStruct = $userService->newUserCreateStruct(
                    $user->getUsername(), $user->getEmail(), "TODO: fix this so that these passwords can not be matched anymore",
                    $this->settings['default_content_language'],
                    $contentTypeService->loadContentTypeByIdentifier($this->settings['user_contenttype'])
                );

                $this->setFieldValuesFromProfile($profile, $userCreateStruct);

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
                    $profile = $user->getProfile();

                    $userUpdateStruct = $userService->newUserUpdateStruct();

                    $this->setFieldValuesFromProfile($profile, $userUpdateStruct);

                    // we use a transaction since there are multiple db operations
                    try {
                        $this->repository->beginTransaction();

                        $repoUser = $userService->updateUser($eZUser, $userUpdateStruct);

                        // fix user groups assignments: first remove unsued current ones, then add new ones
                        $newUserGroups = $this->getGroupsFromProfile($profile);
                        $currentUserGroups = $repoUser->loadUserGroupsOfUser($eZUser);
                        foreach($currentUserGroups as $currentUserGroup) {
                            if (!array_key_exists($currentUserGroup->id, $newUserGroups)) {
                                $userService->unAssignUserFromUserGroup($repoUser, $currentUserGroup);
                            } else {
                                unset($newUserGroups[$currentUserGroup->id]);
                            }
                        }
                        foreach ($newUserGroups as $newUserGroup) {
                            $userService->assignUserToUserGroup($repoUser, $newUserGroup);
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

    /**
     * Load (and possibly create on the fly) all the user groups needed for this user, based on his profile.
     *
     * @param array $profile
     * @return \eZ\Publish\API\Repository\Values\User\UserGroup[] indexed by group id
     */
    abstract protected function getGroupsFromProfile($profile);

    /**
     * @param array $profile
     * @param $userCreateStruct either a create or an update stuct
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
        return $this->profileHash($remoteUser->getProfile()) !== str_replace('ldap_md5:', '', $eZUser->contentInfo->remoteId);
    }

    /**
     * Generates a unique hash for the user profile
     * @param array $profile
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
        $imageFileName = tempnam(sys_get_temp_dir(), $prefix);
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
            unlink($fileName);
        }
    }

    /**
     * Needed to clean up after createTempFile()
     */
    protected function cleanUpAfterUserUpdate()
    {
        foreach ($this->tempFiles as $fileName) {
            unlink($fileName);
        }
    }
}
