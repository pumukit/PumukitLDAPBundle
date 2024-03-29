<?php

namespace Pumukit\LDAPBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_ldap');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('server')
            ->isRequired()
            ->info('LDAP Server DNS address')
            ->end()
            ->scalarNode('host')
            ->isRequired()
            ->info('LDAP host')
            ->end()
            ->scalarNode('port')
            ->isRequired()
            ->info('LDAP port')
            ->end()
            ->integerNode('version')
            ->defaultValue(3)
            ->info('LDAP version')
            ->end()
            ->booleanNode('useSSL')
            ->isRequired()
            ->defaultTrue()
            ->info('LDAP use SSL')
            ->end()
            ->scalarNode('bind_rdn')
            ->defaultNull()
            ->info('LDAP Server DN Search Engine. If not specified, anonymous bind is attempted.')
            ->end()
            ->scalarNode('bind_password')
            ->defaultNull()
            ->info('LDAP Server password. If not specified, anonymous bind is attempted.')
            ->end()
            ->scalarNode('base_dn')
            ->isRequired()
            ->info('LDAP Server DN User')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
