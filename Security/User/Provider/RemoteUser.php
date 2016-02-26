<?php

namespace Kaliop\IdentityManagementBundle\Security\User\Provider;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Kaliop\IdentityManagementBundle\Adapter\ClientInterface;
use Kaliop\IdentityManagementBundle\Security\User\AMSUser as UserClass;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUserProviderInterface;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUser as KaliopRemoteUser;
use eZ\Publish\Core\MVC\Symfony\Security\User\APIUserProviderInterface;
use eZ\Publish\Core\MVC\Symfony\Security\User as eZMVCUser;

class RemoteUser implements UserProviderInterface, RemoteUserProviderInterface
{
    protected $logger;
    protected $eZUserProvider;
    protected $handlerMap;
    protected $container;

    /**
     * @param $eZUserProvider the user provider to which we actually delegate finding eZ User
     * @param array $handlerMap
     */
    public function __construct(APIUserProviderInterface $eZUserProvider, array $handlerMap, $container)
    {
        $this->eZUserProvider = $eZUserProvider;
        $this->handlerMap = $handlerMap;
        $this->container = $container;
    }

    public function setLogger($logger)
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

        // NB: it would be nice to be able to wrap these calls in a try/catch block to fix any error during ez user
        //     account creation, and simply disallow login.
        //     Unfortunately, it seems that if at this stage we return null, the Sf session will be set to a logged-in
        //     user, while eZP will think that it is an anon user. I tried to fix the Sf session so as to prevent the
        //     user from being logged in, without success.
        //     This forces the developer to do validation of the user profile adta gotten from the remote service inside
        //     the client code, which is not as logical/clean...

        // does eZ user exist? If not, create it, else update it
        try {
            $mvcUser = $this->eZUserProvider->loadUserByUsername($remoteUser->getUsername());
            $repoUser = $mvcUser->getAPIUser();
            $this->getHandler($remoteUser)->updateRepoUser($remoteUser, $repoUser);
        } catch (UsernameNotFoundException $e) {
            // we have to create an eZ MVC user out of an eZ Repo user
            $repoUser = $this->getHandler($remoteUser)->createRepoUser($remoteUser);
        }

        return $repoUser;
    }

    protected function getHandler($remoteUser) {
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
     * @return mixed
     * @throws \Exception
     */
    public function getHandlerForClass($class) {
        if (!isset($this->handlerMap[$class])) {
            throw new \Exception("Can not load conversion handler for remote user of class $class");
        }
        return $this->container->get($this->handlerMap[$class]);
    }
}