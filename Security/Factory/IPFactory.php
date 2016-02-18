<?php

namespace Kaliop\IdentityManagementBundle\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Ties together the auth listener and provider for IP-based access
 *
 * @see http://symfony.com/doc/2.3/cookbook/security/custom_authentication_provider.html
 */
class IPFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.ip.'.$id;
        $container
            ->setDefinition($providerId, new DefinitionDecorator('kaliop_identity.security.authentication.provider.ip'))
            ->replaceArgument(0, new Reference($config['mapper']))
            ->replaceArgument(1, new Reference($userProvider))
        ;

        $listenerId = 'security.authentication.listener.ip.'.$id;
        $container->setDefinition($listenerId, new DefinitionDecorator('kaliop_identity.security.authentication.listener.ip'));

        return array($providerId, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'ip_login';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode('mapper')
            ->end();
    }
}