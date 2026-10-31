<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pumukit\LDAPBundle\Controller\PersonController;
use Pumukit\LDAPBundle\Services\LDAPService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 *
 * @coversNothing
 */
class PersonControllerTest extends TestCase
{
    public function testTheSearchIsNotAvailableWhenLdapIsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->ldapService(false))->autocompleteAction(new Request(['term' => 'john']));
    }

    public function testTheButtonIsNotShownWhenLdapIsDisabled(): void
    {
        $response = $this->controller($this->ldapService(false))->buttonAction(new MultimediaObject(), new Role());

        $this->assertSame('', $response->getContent());
    }

    public function testTheSearchReturnsTheNameAndTheEmail(): void
    {
        $ldapService = $this->ldapService(true, true);
        $ldapService->expects($this->once())->method('searchUsers')->with('john')->willReturn([['mail' => 'jdoe@example.org', 'cn' => 'John Doe']]);

        $response = $this->controller($ldapService)->autocompleteAction(new Request(['term' => ' john ']));

        $this->assertSame([['mail' => 'jdoe@example.org', 'cn' => 'John Doe']], json_decode((string) $response->getContent(), true));
    }

    public function testShortTermsAreNotSearched(): void
    {
        $ldapService = $this->ldapService(true, true);
        $ldapService->expects($this->never())->method('searchUsers');

        $response = $this->controller($ldapService)->autocompleteAction(new Request(['term' => 'j']));

        $this->assertSame('[]', $response->getContent());
    }

    public function testAnLdapErrorIsReported(): void
    {
        $ldapService = $this->ldapService(true);
        $ldapService->method('searchUsers')->willThrowException(new \RuntimeException('LDAP bind failed: Invalid credentials'));

        $response = $this->controller($ldapService)->autocompleteAction(new Request(['term' => 'john']));

        $this->assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $this->assertSame('LDAP bind failed: Invalid credentials', $response->getContent());
    }

    public function testTheLinkNeedsAnEmail(): void
    {
        $personService = $this->createStub(PersonService::class);
        $personService->method('getPersonalScopeRoleCode')->willReturn('owner');
        $role = new Role();
        $role->setCod('actor');

        $response = $this->controller($this->ldapService(true), $personService)->linkAction(new Request(), new MultimediaObject(), $role);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function ldapService(bool $configured, bool $mock = false): LDAPService
    {
        $ldapService = $mock ? $this->createMock(LDAPService::class) : $this->createStub(LDAPService::class);
        $ldapService->method('isConfigured')->willReturn($configured);

        return $ldapService;
    }

    private function controller(LDAPService $ldapService, ?PersonService $personService = null): PersonController
    {
        return new PersonController($ldapService, $personService ?? $this->createStub(PersonService::class), new NullLogger());
    }
}
