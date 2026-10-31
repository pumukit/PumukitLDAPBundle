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
    public function testADisabledServiceIsNotConfiguredAndDoesNotConnect(): void
    {
        $service = $this->service(enabled: false);

        $this->assertFalse($service->isConfigured());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LDAP is not enabled');

        $service->getInfoFromEmail('jdoe@example.org');
    }

    public function testAnEnabledServiceWithAServerIsConfigured(): void
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
        $service = $this->service(enabled: false);

        $this->assertSame([], $service->searchUsers('  '));
        $this->assertSame([], $service->getListUsers());
    }

    private function service(bool $enabled = true): LDAPService
    {
        return new LDAPService('ldap://127.0.0.1:1', 'ou=people,dc=example,dc=org', new NullLogger(), enabled: $enabled);
    }
}
