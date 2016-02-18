<?php

namespace Kaliop\IdentityManagementBundle\Security\Helper;

use Kaliop\IdentityManagementBundle\Security\Interfaces\IpToUserMapper as MapperInterface;

/**
 * A very, very simplistic implementation of the interface
 */
class IpToUserMapper implements MapperInterface
{
    protected $ipList = array();

    /**
     * @param array $ipList key: ip, value: user id
     */
    public function __construct(array $ipList)
    {
        $this->ipList = $ipList;
    }

    public function getInternalUser($ip)
    {
        return isset($this->ipList[$ip]) ? $this->ipList[$ip] : false;
    }
}
