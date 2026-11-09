<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\LDAPBundle\Command\FindUserCommand;
use Pumukit\LDAPBundle\Controller\PersonController;
use Pumukit\LDAPBundle\DependencyInjection\PumukitLDAPExtension;
use Pumukit\LDAPBundle\EventListener\LoginListener;
use Pumukit\LDAPBundle\Routing\RouteLoader;
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

    public function testTheDisabledBundleOnlyRegistersTheRouteLoader(): void
    {
        $container = $this->load(['enable' => false, 'person_button' => true]);

        $this->assertSame([RouteLoader::class], $this->ldapServices($container));
        $this->assertFalse($container->hasParameter('pumukit.person_picker_templates'));
    }

    public function testTheEnabledBundleRegistersItsServices(): void
    {
        $container = $this->load(self::ENABLED);

        foreach ([RouteLoader::class, LDAPService::class, LoginListener::class, FindUserCommand::class, PersonController::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
    }

    public function testThePersonButtonIsNotAddedByDefault(): void
    {
        $this->assertFalse($this->load(self::ENABLED)->hasParameter('pumukit.person_picker_templates'));
    }

    public function testThePersonButtonIsAddedToTheTemplatesOfTheOtherBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukit.person_picker_templates', ['@Other/button.html.twig']);
        (new PumukitLDAPExtension())->load([[...self::ENABLED, 'person_button' => true]], $container);

        $this->assertSame(['@Other/button.html.twig', PumukitLDAPExtension::PERSON_PICKER_TEMPLATE], $container->getParameter('pumukit.person_picker_templates'));
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
