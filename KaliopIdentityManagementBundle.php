<?php

namespace Kaliop\IdentityManagementBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Kaliop\IdentityManagementBundle\Security\Factory\IPFactory;
use Kaliop\IdentityManagementBundle\Security\Factory\RemoteUserFactory;

class KaliopIdentityManagementBundle extends Bundle {

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // register the factories which set up the custom auth providers
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new IPFactory());
        $extension->addSecurityListenerFactory(new RemoteUserFactory());
    }
}
