<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\Services;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pumukit\LDAPBundle\Services\LDAPService;

/**
 * @internal
 *
 * @coversNothing
 */
class LDAPServiceTest extends TestCase
{
    public function testAServiceWithoutServerIsNotConfiguredAndDoesNotConnect(): void
    {
        $service = $this->service('');

        $this->assertFalse($service->isConfigured());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The LDAP server is not configured');

        $service->getInfoFromEmail('jdoe@example.org');
    }

    public function testAServiceWithAServerIsConfigured(): void
    {
        $this->assertTrue($this->service()->isConfigured());
    }

    public function testAnAttributeThatCouldChangeTheFilterIsRejectedBeforeConnecting(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->getInfoFrom('uid)(objectClass=*', 'jdoe');
    }

    public function testAnEmptySearchDoesNotConnect(): void
    {
        $service = $this->service('');

        $this->assertSame([], $service->searchUsers('  '));
        $this->assertSame([], $service->getListUsers());
    }

    private function service(string $server = 'ldap://127.0.0.1:1'): LDAPService
    {
        return new LDAPService($server, 'ou=people,dc=example,dc=org', new NullLogger());
    }
}
