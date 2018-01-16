<?php

namespace Kaliop\IdentityManagementBundle\Adapter;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUser;

interface ClientInterface
{
    /**
     * @param string $login
     * @param string $password
     * @return RemoteUser
     * @throws AuthenticationException
     */
    public function authenticateUser($login, $password);
}
