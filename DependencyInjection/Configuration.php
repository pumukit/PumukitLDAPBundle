<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const REQUIRED = ['server', 'base_dn'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_ldap');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->validate()
            ->ifTrue(static fn (array $config): bool => $config['enable'] && [] !== self::missing($config))
            ->then(static function (array $config): never {
                throw new \InvalidArgumentException('pumukit_ldap needs '.implode(', ', self::missing($config)).' when it is enabled');
            })
            ->end()
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('Enable the LDAP lookups, the person search and the login listener. If false, nothing of the bundle is loaded, not even its routes, and server and base_dn are not required')
            ->end()
            ->scalarNode('server')
            ->defaultNull()
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
            ->defaultNull()
            ->info('Base DN of the users')
            ->end()
            ->booleanNode('person_button')
            ->defaultFalse()
            ->info('Show the "New from LDAP" button in the People tab of the multimedia objects')
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

    private static function missing(array $config): array
    {
        return array_values(array_filter(self::REQUIRED, static fn (string $key): bool => '' === trim((string) $config[$key])));
    }
}
