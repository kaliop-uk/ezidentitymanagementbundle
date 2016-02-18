<?php

namespace Kaliop\IdentityManagementBundle\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @see http://www.caillarec-coste.fr/symfony2-introduire-une-logique-personnalise-durant-le-login/
 */
class RemoteUserFactory extends FormLoginFactory //implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.remoteuser.'.$id;
        $container
            ->setDefinition($providerId, new DefinitionDecorator('kaliop_identity.security.authentication.provider.remoteuser'))
            ->replaceArgument(0, new Reference($config['client']))
            ->replaceArgument(1, new Reference($userProvider))
            ->replaceArgument(2, $id)
        ;

        //$listenerId = 'security.authentication.listener.ams.'.$id;
        //$container->setDefinition($listenerId, new DefinitionDecorator('kaliop_identity.security.authentication.listener.remoteuser'));
        $listenerId = $this->createListener($container, $id, $config, $userProvider);

        return array($providerId, $listenerId, $defaultEntryPoint);
    }

    protected function getListenerId()
    {
        return 'kaliop_identity.security.authentication.listener.remoteuser';
    }

    /**
     * Defines the position at which the provider is called.
     * Possible values: pre_auth, form, http, and remember_me.
     *
     * @return string
     */
    public function getPosition()
    {
        return 'form';
    }

    public function getKey()
    {
        return 'remoteuser_login';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
            ->scalarNode('client')
            ->end();
    }
}