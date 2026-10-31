<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pumukit\LDAPBundle\Event\LdapLoginEvent;
use Pumukit\LDAPBundle\EventListener\LoginListener;
use Pumukit\LDAPBundle\Services\LDAPService;
use Pumukit\SchemaBundle\Document\User;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @internal
 *
 * @coversNothing
 */
class LoginListenerTest extends TestCase
{
    private const ENTRY = [
        'dn' => 'uid=jdoe,ou=people,dc=example,dc=org',
        'uid' => ['count' => 1, 0 => 'jdoe'],
        'ou' => ['count' => 2, 0 => 'PDI', 1 => 'Teachers'],
        'count' => 2,
    ];

    public function testTheLdapEntryOfTheUserIsDispatchedAfterTheLogin(): void
    {
        $ldapService = $this->ldapService();
        $ldapService->expects($this->once())->method('getInfoFrom')->with('uid', 'jdoe')->willReturn(self::ENTRY);
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher)->onInteractiveLogin($this->login($this->user()));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('jdoe', $event->getUser()->getUsername());
        $this->assertSame('uid=jdoe,ou=people,dc=example,dc=org', $event->getDn());
        $this->assertSame('PDI', $event->getValue('OU'));
        $this->assertSame(['PDI', 'Teachers'], $event->getValues('ou'));
        $this->assertSame([], $event->getValues('mail'));
    }

    public function testTheEmailCanBeUsedToFindTheEntry(): void
    {
        $ldapService = $this->ldapService();
        $ldapService->expects($this->once())->method('getInfoFrom')->with('mail', 'jdoe@example.org')->willReturn(self::ENTRY);
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher, attribute: 'mail', field: 'email')->onInteractiveLogin($this->login($this->user()));

        $this->assertCount(1, $events);
    }

    public function testNothingIsLookedUpWhenTheLoginLookupIsDisabled(): void
    {
        $ldapService = $this->ldapService();
        $ldapService->expects($this->never())->method('getInfoFrom');
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher, enabled: false)->onInteractiveLogin($this->login($this->user()));

        $this->assertCount(0, $events);
    }

    public function testAnLdapErrorDoesNotBreakTheLogin(): void
    {
        $ldapService = $this->ldapService(false);
        $ldapService->method('getInfoFrom')->willThrowException(new \RuntimeException('LDAP bind failed'));
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher)->onInteractiveLogin($this->login($this->user()));

        $this->assertCount(0, $events);
    }

    public function testAUserMissingInLdapIsNotDispatched(): void
    {
        $ldapService = $this->ldapService(false);
        $ldapService->method('getInfoFrom')->willReturn(false);
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher)->onInteractiveLogin($this->login($this->user()));

        $this->assertCount(0, $events);
    }

    public function testUsersThatAreNotPumukitUsersAreIgnored(): void
    {
        $ldapService = $this->ldapService();
        $ldapService->expects($this->never())->method('getInfoFrom');
        [$dispatcher, $events] = $this->dispatcher();

        $this->listener($ldapService, $dispatcher)->onInteractiveLogin($this->login(new InMemoryUser('api', null)));

        $this->assertCount(0, $events);
    }

    private function ldapService(bool $mock = true): LDAPService
    {
        $ldapService = $mock ? $this->createMock(LDAPService::class) : $this->createStub(LDAPService::class);
        $ldapService->method('isConfigured')->willReturn(true);

        return $ldapService;
    }

    private function dispatcher(): array
    {
        $events = new \ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LdapLoginEvent::class, static function (LdapLoginEvent $event) use ($events): void {
            $events[] = $event;
        });

        return [$dispatcher, $events];
    }

    private function listener(LDAPService $ldapService, EventDispatcher $dispatcher, bool $enabled = true, string $attribute = 'uid', string $field = 'username'): LoginListener
    {
        return new LoginListener($ldapService, $dispatcher, new NullLogger(), $enabled, $attribute, $field);
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('jdoe');
        $user->setEmail('jdoe@example.org');

        return $user;
    }

    private function login(object $user): InteractiveLoginEvent
    {
        return new InteractiveLoginEvent(new Request(), new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
    }
}
