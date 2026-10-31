<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pumukit\LDAPBundle\Command\CheckConnectionCommand;
use Pumukit\LDAPBundle\Command\FindUserCommand;
use Pumukit\LDAPBundle\Services\LDAPService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class CommandsTest extends TestCase
{
    private const ENTRY = [
        'dn' => 'uid=jdoe,ou=people,dc=example,dc=org',
        'count' => 3,
        0 => 'cn',
        'cn' => ['count' => 1, 0 => 'John Doe'],
        1 => 'userpassword',
        'userpassword' => ['count' => 1, 0 => '{SSHA}hash'],
        2 => 'ou',
        'ou' => ['count' => 2, 0 => 'PDI', 1 => 'Teachers'],
    ];

    public function testTheConnectionCheckSucceeds(): void
    {
        $ldapService = $this->createMock(LDAPService::class);
        $ldapService->expects($this->once())->method('checkConnection')->willReturn(true);
        $tester = new CommandTester(new CheckConnectionCommand($ldapService, 'ldaps://ldap.example.org', null));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('anonymous', $tester->getDisplay());
    }

    public function testTheConnectionCheckReportsTheLdapError(): void
    {
        $ldapService = $this->createStub(LDAPService::class);
        $ldapService->method('checkConnection')->willThrowException(new \RuntimeException("LDAP bind failed: Can't contact LDAP server"));
        $tester = new CommandTester(new CheckConnectionCommand($ldapService, 'ldaps://ldap.example.org', 'cn=admin'));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString("Can't contact LDAP server", $tester->getDisplay());
    }

    public function testTheEntryIsShownWithThePasswordsHidden(): void
    {
        $ldapService = $this->createMock(LDAPService::class);
        $ldapService->expects($this->once())->method('getInfoFrom')->with('mail', 'jdoe@example.org')->willReturn(self::ENTRY);
        $tester = new CommandTester(new FindUserCommand($ldapService));

        $this->assertSame(Command::SUCCESS, $tester->execute(['value' => 'jdoe@example.org', '--attribute' => 'mail', '--json' => true]));
        $this->assertSame([
            'dn' => 'uid=jdoe,ou=people,dc=example,dc=org',
            'cn' => ['John Doe'],
            'ou' => ['PDI', 'Teachers'],
            'userpassword' => ['******'],
        ], json_decode($tester->getDisplay(), true));
    }

    public function testTheTableShowsEveryValue(): void
    {
        $ldapService = $this->createStub(LDAPService::class);
        $ldapService->method('getInfoFrom')->willReturn(self::ENTRY);
        $tester = new CommandTester(new FindUserCommand($ldapService));

        $this->assertSame(Command::SUCCESS, $tester->execute(['value' => 'jdoe']));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Teachers', $display);
        $this->assertStringNotContainsString('{SSHA}hash', $display);
    }

    public function testAMissingUserFails(): void
    {
        $ldapService = $this->createStub(LDAPService::class);
        $ldapService->method('getInfoFrom')->willReturn(false);
        $tester = new CommandTester(new FindUserCommand($ldapService));

        $this->assertSame(Command::FAILURE, $tester->execute(['value' => 'nobody']));
        $this->assertStringContainsString('uid=nobody', $tester->getDisplay());
    }
}
