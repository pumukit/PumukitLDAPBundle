<?php

namespace Pumukit\LDAPBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PumukitLDAPExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_ldap.server', $config['server']);
        $container->setParameter('pumukit_ldap.host', $config['host']);
        $container->setParameter('pumukit_ldap.port', $config['port']);
        $container->setParameter('pumukit_ldap.useSSL', $config['useSSL']);
        $container->setParameter('pumukit_ldap.bind_rdn', $config['bind_rdn']);
        $container->setParameter('pumukit_ldap.bind_password', $config['bind_password']);
        $container->setParameter('pumukit_ldap.base_dn', $config['base_dn']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
}
