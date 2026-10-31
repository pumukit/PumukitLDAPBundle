<?php

declare(strict_types=1);

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
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('Enable the LDAP lookups, the person search and the login listener')
            ->end()
            ->scalarNode('server')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('LDAP server URI, e.g. ldap://ldap.example.org:389 or ldaps://ldap.example.org:636')
            ->end()
            ->enumNode('version')
            ->values([2, 3])
            ->defaultValue(3)
            ->info('LDAP protocol version')
            ->end()
            ->booleanNode('start_tls')
            ->defaultFalse()
            ->info('Upgrade a ldap:// connection with StartTLS')
            ->end()
            ->scalarNode('bind_rdn')
            ->defaultNull()
            ->info('DN used to search. If not specified, anonymous bind is attempted.')
            ->end()
            ->scalarNode('bind_password')
            ->defaultNull()
            ->info('Password of bind_rdn')
            ->end()
            ->scalarNode('base_dn')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Base DN of the users')
            ->end()
            ->arrayNode('on_login')
            ->addDefaultsIfNotSet()
            ->info('Look up the user in LDAP after every interactive login and dispatch LdapLoginEvent')
            ->children()
            ->booleanNode('enable')
            ->defaultFalse()
            ->end()
            ->scalarNode('ldap_attribute')
            ->defaultValue('uid')
            ->cannotBeEmpty()
            ->info('LDAP attribute matched against the user field')
            ->end()
            ->enumNode('user_field')
            ->values(['username', 'email'])
            ->defaultValue('username')
            ->info('PuMuKIT user field used to find the LDAP entry')
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
