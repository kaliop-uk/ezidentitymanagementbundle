<?php

namespace Kaliop\IdentityManagementBundle\Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Kaliop\IdentityManagementBundle\Security\Authentication\Token\IPToken;

class IPAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $ipToUserMapper;
    protected $userProvider;

    public function __construct($ipToUserMapper, UserProviderInterface $userProvider)
    {
        $this->ipToUserMapper = $ipToUserMapper;
        $this->userProvider = $userProvider;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof \Kaliop\IdentityManagementBundle\Security\Authentication\Token\IPToken;
    }

    public function authenticate(TokenInterface $token)
    {
        $user = $this->ipToUserMapper->getInternalUser($token->getClientIp());
        if ($user) {
            $user = $this->userProvider->loadUserByUsername($user);
        }
        if ($user)
        {
            $authenticatedToken = new IPToken($user->getRoles());
            $authenticatedToken->setClientIp($token->getClientIp())->setUser($user);
            $authenticatedToken->setAuthenticated(true);

            return $authenticatedToken;
        }

        throw new AuthenticationException('No valid IP auth found');
    }
}