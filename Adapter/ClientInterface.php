<?php

namespace Kaliop\IdentityManagementBundle\Adapter;

use Kaliop\IdentityManagementBundle\Security\User\RemoteUser;

interface ClientInterface
{
    /**
     * @param string $login
     * @param string $password
     * @return RemoteUser
     * @throws BadCredentialsException|AuthenticationServiceException
     */
    public function authenticateUser($login, $password);
}