<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\LDAPBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    public function testTheMinimalConfigurationGetsTheDefaults(): void
    {
        $config = $this->process(['server' => 'ldaps://ldap.example.org', 'base_dn' => 'ou=people,dc=example,dc=org']);

        $this->assertTrue($config['enable']);
        $this->assertSame(3, $config['version']);
        $this->assertFalse($config['start_tls']);
        $this->assertNull($config['bind_rdn']);
        $this->assertNull($config['bind_password']);
        $this->assertSame(['enable' => false, 'ldap_attribute' => 'uid', 'user_field' => 'username'], $config['on_login']);
    }

    public function testTheOptionsOfThePreviousVersionAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['server' => 'ldap://ldap', 'base_dn' => 'dc=example', 'host' => 'ldap', 'port' => 389, 'useSSL' => false]);
    }

    public function testTheUserFieldIsUsernameOrEmail(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['server' => 'ldap://ldap', 'base_dn' => 'dc=example', 'on_login' => ['user_field' => 'fullname']]);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
