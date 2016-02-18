<?php

namespace Kaliop\IdentityManagementBundle\Traits;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trait User {

    protected $securityToken = null;

    protected function isAuthenticated() {
        if( $this->securityToken == null ) {
            $securityToken = $this->container->get( 'security.token_storage' )->getToken();
            if( $securityToken instanceof TokenInterface ) {
                $this->securityToken = $securityToken;
            }
        }

        return $this->securityToken->isAuthenticated() === true && count( $this->securityToken->getRoles() );
    }
}
