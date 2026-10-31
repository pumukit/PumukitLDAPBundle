<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\DependencyInjection;

use Pumukit\CoreBundle\DependencyInjection\FrontendAssets;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class PumukitLDAPExtension extends Extension implements PrependExtensionInterface
{
    public const LOG_CHANNEL = 'ldap';

    public function prepend(ContainerBuilder $container): void
    {
        FrontendAssets::register($container, dirname(__DIR__), '@pumukit/ldap');

        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => [self::LOG_CHANNEL],
                'handlers' => [
                    self::LOG_CHANNEL => [
                        'type' => 'rotating_file',
                        'path' => '%kernel.logs_dir%/ldap.log',
                        'level' => 'info',
                        'max_files' => 30,
                        'channels' => [self::LOG_CHANNEL],
                        'bubble' => false,
                        'priority' => 100,
                    ],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_ldap.enable', $config['enable']);
        $container->setParameter('pumukit_ldap.server', $config['server']);
        $container->setParameter('pumukit_ldap.version', $config['version']);
        $container->setParameter('pumukit_ldap.start_tls', $config['start_tls']);
        $container->setParameter('pumukit_ldap.bind_rdn', $config['bind_rdn']);
        $container->setParameter('pumukit_ldap.bind_password', $config['bind_password']);
        $container->setParameter('pumukit_ldap.base_dn', $config['base_dn']);
        $container->setParameter('pumukit_ldap.on_login.enable', $config['on_login']['enable']);
        $container->setParameter('pumukit_ldap.on_login.ldap_attribute', $config['on_login']['ldap_attribute']);
        $container->setParameter('pumukit_ldap.on_login.user_field', $config['on_login']['user_field']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_ldap.yaml');
    }
}
