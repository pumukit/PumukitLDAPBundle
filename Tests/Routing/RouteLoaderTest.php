<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pumukit\LDAPBundle\Routing\RouteLoader;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @coversNothing
 */
class RouteLoaderTest extends TestCase
{
    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, $this->load(false));
    }

    public function testTheControllerRoutesAreLoadedWhenItIsEnabled(): void
    {
        $routes = $this->load(true);

        $this->assertSame('/admin/ldap/person/listautocomplete/{mmId}/{roleId}', $routes->get('pumukit_ldap_person_listautocomplete')?->getPath());
        $this->assertSame('/admin/ldap/person/autocomplete', $routes->get('pumukit_ldap_person_autocomplete')?->getPath());
        $this->assertSame('/admin/ldap/person/link/{mmId}/{roleId}', $routes->get('pumukit_ldap_person_link')?->getPath());
    }

    private function load(bool $enabled): RouteCollection
    {
        $loader = new RouteLoader($enabled);
        $loader->setResolver(new LoaderResolver([$loader, new AttributeDirectoryLoader(new FileLocator(dirname(__DIR__, 2)), new AttributeRouteControllerLoader())]));

        return $loader->load('Controller/', RouteLoader::TYPE);
    }
}
