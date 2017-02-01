<?php

namespace Kaliop\IdentityManagementBundle\Security\Authentication\Provider;

use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\Symfony\Security\User as EzUser;
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * We "should" subclass eZ\Publish\Core\MVC\Symfony\Security\Authentication\RepositoryAuthenticationProvider here,
 * but that class has the $repository member as private, so there is little point in doing that, and we subclass
 * directly its parent
 */
class RepositoryAuthenticationProvider extends DaoAuthenticationProvider
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        if (!$user instanceof EzUser) {
            return parent::checkAuthentication($user, $token);
        }

        // $currentUser can either be an instance of UserInterface or just the username (e.g. during form login).
        /** @var EzUser|string $currentUser */
        $currentUser = $token->getUser();
        if ($currentUser instanceof UserInterface) {
            if ($currentUser->getPassword() !== $user->getPassword()) {
                throw new BadCredentialsException('The credentials were changed from another session.');
            }

            $apiUser = $currentUser->getAPIUser();
        } else {
            try {
                /// @bug this will fail if any user has as @ character in their login field and wants to log in using that...
                /*if (preg_match('#(.)*@(.)*#',$token->getUsername())) {
                    $user = $this->repository->getUserService()->loadUsersByEmail($token->getUsername());
                    /** @var \eZ\Publish\Core\Repository\Values\User\User $user * /
                    $user = $user[0];
                    $token = new UsernamePasswordToken(
                        $user->login, $token->getCredentials(), $token->getProviderKey(), $token->getRoles()
                    );

                }*/

                $apiUser = $this->repository->getUserService()->loadUserByCredentials($token->getUsername(), $token->getCredentials());
            } catch (NotFoundException $e) {
                try {
                    $users = $this->repository->getUserService()->loadUsersByEmail($token->getUsername());
                    if (!count($users)) {
                        throw new NotFoundException('User', $token->getUsername());
                    }
                    /// @todo log a warning if many users do match the email
                    $userLogin = $users[0]->login;
                    $apiUser = $this->repository->getUserService()
                        ->loadUserByCredentials($userLogin, $token->getCredentials());
                } catch (NotFoundException $e) {
                    throw new BadCredentialsException('Invalid credentials', 0, $e);
                }
            }
        }

        // Finally inject current user in the Repository
        $this->repository->setCurrentUser($apiUser);
    }
}
