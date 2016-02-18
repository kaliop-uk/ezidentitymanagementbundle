<?php

namespace Kaliop\IdentityManagementBundle\Security\User\Provider;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\Core\MVC\Symfony\Security\User;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use eZ\Publish\Core\MVC\Symfony\Security\User\Provider;

/**
 * Used to log in users by either their email or login
 */
class EmailUser extends Provider
{
    /**
     * Loads the user for the given user 'ID'.
     * $user can be either the user ID or an instance of \eZ\Publish\Core\MVC\Symfony\Security\User
     * (anonymous user we try to check access via SecurityContext::isGranted()).
     *
     * @param string|\eZ\Publish\Core\MVC\Symfony\Security\User $user Either the user ID to load an instance of User object. A value of -1 represents an anonymous user.
     *
     * @return \eZ\Publish\Core\MVC\Symfony\Security\UserInterface
     *
     * @throws \Symfony\Component\Security\Core\Exception\UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($user)
    {
        try {
            // SecurityContext always tries to authenticate anonymous users when checking granted access.
            // In that case $user is an instance of \eZ\Publish\Core\MVC\Symfony\Security\User.
            // We don't need to reload the user here.
            if ($user instanceof UserInterface) {
                return $user;
            }
            // 1st try to find user by login
            return new User($this->repository->getUserService()->loadUserByLogin($user), array('ROLE_USER'));
        } catch (NotFoundException $e) {
            // Then we try to find the user via email address
            $users = $this->repository->getUserService()->loadUsersByEmail($user);
            if (!count($users)) {
                throw new UsernameNotFoundException();
            }
            /// @todo log a warning if many users do match the email
            return new User($users[0], array('ROLE_USER'));
        }
    }
}