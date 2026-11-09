<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\LDAPBundle\Command\FindUserCommand;
use Pumukit\LDAPBundle\Controller\PersonController;
use Pumukit\LDAPBundle\DependencyInjection\PumukitLDAPExtension;
use Pumukit\LDAPBundle\EventListener\DisabledListener;
use Pumukit\LDAPBundle\EventListener\LoginListener;
use Pumukit\LDAPBundle\Services\LDAPService;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitLDAPExtensionTest extends TestCase
{
    private const ENABLED = ['server' => 'ldaps://ldap.example.org', 'base_dn' => 'ou=people,dc=example,dc=org'];

    public function testTheDisabledBundleOnlyRegistersTheNotFoundListener(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([DisabledListener::class], $this->ldapServices($container));
    }

    public function testTheEnabledBundleRegistersItsServicesWithoutTheNotFoundListener(): void
    {
        $container = $this->load(self::ENABLED);

        foreach ([LDAPService::class, LoginListener::class, FindUserCommand::class, PersonController::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertFalse($container->hasDefinition(DisabledListener::class));
    }

    public function testTheDisabledBundleDoesNotPrependTheLogChannel(): void
    {
        $this->assertSame([], $this->prepend(['enable' => false])->getExtensionConfig('monolog'));
    }

    public function testTheEnabledBundlePrependsTheLogChannel(): void
    {
        $this->assertSame([PumukitLDAPExtension::LOG_CHANNEL], $this->prepend(self::ENABLED)->getExtensionConfig('monolog')[0]['channels']);
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PumukitLDAPExtension())->load([$config], $container);

        return $container;
    }

    private function prepend(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new MonologExtension());
        $extension = new PumukitLDAPExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('pumukit_ldap', $config);
        $extension->prepend($container);

        return $container;
    }

    private function ldapServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\LDAPBundle\\')));
    }
}
