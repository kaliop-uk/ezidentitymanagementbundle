<?php

namespace Kaliop\IdentityManagementBundle\Security\User\Provider;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Kaliop\IdentityManagementBundle\Security\User\AMSUser as UserClass;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUserProviderInterface;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUser as KaliopRemoteUser;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUserHandlerInterface;
use eZ\Publish\Core\MVC\Symfony\Security\User\APIUserProviderInterface;
use eZ\Publish\Core\MVC\Symfony\Security\User as eZMVCUser;
use Psr\Log\LoggerInterface;

class RemoteUser implements UserProviderInterface, RemoteUserProviderInterface
{
    protected $logger;
    protected $eZUserProvider;
    protected $handlerMap;
    protected $container;

    /**
     * @param APIUserProviderInterface $eZUserProvider the user provider to which we actually delegate finding eZ User
     * @param array $handlerMap
     */
    public function __construct(APIUserProviderInterface $eZUserProvider, array $handlerMap, $container)
    {
        $this->eZUserProvider = $eZUserProvider;
        $this->handlerMap = $handlerMap;
        $this->container = $container;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @todo throw an exception ?
     * @param $username
     * @return UserInterface
     */
    public function loadUserByUsername($username)
    {
    }

    /**
     * This method is called *on every page after the user logged in*.
     * We do not want to call the remote ws on every page.
     * We 'might' check in the eZ db if the user is still there and/or enabled, BUT even that might be unnecessary, as
     * the remoteuser gets converted to an ezmvcuser by the listener, which means this is only called upon login?
     *
     * @param UserInterface $user
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof \Kaliop\IdentityManagementBundle\Security\User\RemoteUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $user;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        $supportedClass = 'Kaliop\IdentityManagementBundle\Security\User\RemoteUser';
        return $class === $supportedClass || is_subclass_of($class, $supportedClass);
    }

    public function loadAPIUserByRemoteUser(KaliopRemoteUser $remoteUser)
    {
        $repoUser = null;
        $userHandler = $this->getHandler($remoteUser);

        // does eZ user exist? If not, create it, else update it
        // NB: it would be nice to be able to wrap these calls in a try/catch block to fix any error during ez user
        //     account creation/update, and simply disallow login.
        //     Unfortunately, it seems that if at this stage we return null, the Sf session will be set to a logged-in
        //     user, while eZP will think that it is an anon user. I tried to fix the Sf session so as to prevent the
        //     user from being logged in, without success.
        //     This forces the developer to do validation of the user profile data gotten from the remote service inside
        //     the client code, which is not as logical/clean...
        try {
            $repoUser = $userHandler->loadAPIUserByRemoteUser($remoteUser);
            if ($repoUser === false) {
                // we have to create an eZ MVC user out of an eZ Repo user
                $repoUser = $userHandler->createRepoUser($remoteUser);
            } else {
                $userHandler->updateRepoUser($remoteUser, $repoUser);
            }

            // In case any post-processing is needed, give the user-handler a chance to execute it without the need to
            // register further listeners
            if (is_callable(array($userHandler, 'onRemoteUserLogin'))) {
                $userHandler->onRemoteUserLogin($remoteUser, $repoUser);
            }

        } catch (\Exception $e) {
            if ($this->logger) $this->logger->error("Unexpected error while finding/creating/updating repo user from data gotten from remote service: " . $e->getMessage());
            throw $e;
        }

        return $repoUser;
    }

    /**
     * @param KaliopRemoteUser $remoteUser
     * @return RemoteUserHandlerInterface
     * @throws \Exception
     */
    protected function getHandler($remoteUser)
    {
        $class = get_class($remoteUser);
        if (!isset($this->handlerMap[$class])) {
            throw new \Exception("Can not load conversion handler for remote user of class $class");
        }
        return $this->container->get($this->handlerMap[$class]);
    }

    /**
     * A courtesy method, if some other service wants to retrieve a remote-user handler for a given php class.
     * Useful to retrieve the remote-user handler before the actual creation of the actual remote-user object, which
     * allows f.e. to put in the remote-user handler some validation code
     *
     * @param string $class a php class name
     * @return RemoteUserHandlerInterface
     * @throws \Exception
     */
    public function getHandlerForClass($class)
    {
        if (!isset($this->handlerMap[$class])) {
            throw new \Exception("Can not load conversion handler for remote user of class $class");
        }
        return $this->container->get($this->handlerMap[$class]);
    }
}