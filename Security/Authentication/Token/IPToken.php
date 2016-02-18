<?php

namespace Kaliop\IdentityManagementBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * @todo review which other methods of the base class we have to reimplement...
 */
class IPToken extends AbstractToken
{
    protected $clientIp;

    public function setClientIp($ip)
    {
        $this->clientIp = $ip;
        return $this;
    }

    public function getClientIp()
    {
        return $this->clientIp;
    }

    /**
     * @todo verify - is this correct?
     */
    public function getCredentials()
    {
        return '';
    }
}