<?php

namespace Kaliop\IdentityManagementBundle\Security\Interfaces;

interface IpToUserMapper
{
    /**
     * @param string $ip
     * @return null|string the user identifier - normally either login or email (exactly what is suppported depends on the user provider)
     */
    public function getInternalUser($ip);
}
