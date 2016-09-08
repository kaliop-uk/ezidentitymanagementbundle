<?php

namespace Kaliop\IdentityManagementBundle\Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Kaliop\IdentityManagementBundle\Adapter\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Role\SwitchUserRole;

/**
 * @todo:
 *        4. save locally the wsdl for perfs, as it is downloaded when building the container!
 *        5. it seems that this authenticator gets called AFTER the eZ one... try to revert the config in security.yml?
 */
class RemoteUserAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $hideUserNotFoundExceptions;
    //protected $userChecker;
    protected $providerKey;
    protected $client;
    protected $userProvider;
    protected $logger;

    public function __construct(ClientInterface $client, UserProviderInterface $userProvider, $providerKey, $hideUserNotFoundExceptions = true)
    {
        $this->client = $client;
        $this->providerKey = $providerKey;
        $this->hideUserNotFoundExceptions = $hideUserNotFoundExceptions;
        $this->userProvider = $userProvider;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof UsernamePasswordToken && $this->providerKey === $token->getProviderKey();
    }

    /**
     * Reimplemented differently from what UserAuthenticationProvider does because we do not have its logic - instead of
     * fetch 1st, then check pwd, we do fetch-while-checking-pwd
     *
     * @param TokenInterface $token
     * @return UsernamePasswordToken|void
     * @throws AuthenticationServiceException
     * @throws UsernameNotFoundException
     * @throws \Exception
     *
     * @see DaoAuthenticationProvider
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return;
        }

        /// @todo throw a BadCredentialsException instead ?
        $username = $token->getUsername();
        if ('' === $username || null === $username) {
            $username = 'NONE_PROVIDED';
        }

        // we can not fetch the user 1st based on his login
        /* try {
            $user = $this->retrieveUser($username, $token);
        } catch (UsernameNotFoundException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }
            $e->setUsername($username);

            throw $e;
        }

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException('retrieveUser() must return a UserInterface.');
        }*/

        try {
            //$this->userChecker->checkPreAuth($user);
            $user = $this->retrieveUserAndCheckAuthentication($token);
            /// @todo !important reintroduce this check?
            //$this->userChecker->checkPostAuth($user);
        } catch (BadCredentialsException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }

            throw $e;
        }

        $authenticatedToken = new UsernamePasswordToken($user, $token->getCredentials(), $this->providerKey, $this->getRoles($user, $token));
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * @param UsernamePasswordToken $token
     * @return mixed|UserInterface
     */
    protected function retrieveUserAndCheckAuthentication(UsernamePasswordToken $token)
    {
        $currentUser = $token->getUser();
        if ($currentUser instanceof UserInterface) {

            /// @todo check if this is a good idea or not: keeping user password in the token ? Maybe encrypt it!
            if ($currentUser->getPassword() !== $token->getCredentials()) {
                throw new BadCredentialsException('The credentials were changed from another session.');
            }
            return $currentUser;

        } else {

            /// @todo !important might want to throw AuthenticationCredentialsNotFoundException instead?
            if ('' === ($presentedUsername = $token->getUsername())) {
                throw new BadCredentialsException('The presented email cannot be empty.');
            }

            if ('' === ($presentedPassword = $token->getCredentials())) {
                throw new BadCredentialsException('The presented password cannot be empty.');
            }

            // communication errors and config errors should be logged/handled by the client
            try {

                $user = $this->client->AuthenticateUser($presentedUsername, $presentedPassword);
                // the client should return a UserInterface, no need for us to use a userProvider
                //$user = $this->userProvider->loadUserByUsername($username);
                return $user;

            } catch(\Exception $e) {
                throw new BadCredentialsException('The presented username or password is invalid.');
            }

            // no need to check the password after loading the user: the remote ws does that
            /*if (!$this->encoderFactory->getEncoder($user)->isPasswordValid($user->getPassword(), $presentedPassword, $user->getSalt())) {
                throw new BadCredentialsException('The presented password is invalid.');
            }*/
        }
    }

    /**
     * Copied from UserAuthenticationProvider
     */
    protected function getRoles(UserInterface $user, TokenInterface $token)
    {
        $roles = $user->getRoles();

        foreach ($token->getRoles() as $role) {
            if ($role instanceof SwitchUserRole) {
                $roles[] = $role;

                break;
            }
        }

        return $roles;
    }
}
