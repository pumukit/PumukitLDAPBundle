<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Controller;

use Doctrine\Bundle\MongoDBBundle\Attribute\MapDocument;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Pumukit\LDAPBundle\Services\LDAPService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Security\Permission;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ldap/person')]
#[IsGranted(Permission::ACCESS_MULTIMEDIA_SERIES)]
#[WithMonologChannel('ldap')]
class PersonController extends AbstractController
{
    public function __construct(
        private readonly LDAPService $ldapService,
        private readonly PersonService $personService,
        private readonly LoggerInterface $logger,
    ) {}

    public function buttonAction(#[MapDocument(id: 'mmId')] MultimediaObject $multimediaObject, #[MapDocument(id: 'roleId')] Role $role): Response
    {
        if (!$this->ldapService->isConfigured()) {
            return new Response();
        }

        return $this->render('@PumukitLDAP/Person/button.html.twig', [
            'mm' => $multimediaObject,
            'role' => $role,
        ]);
    }

    #[Route('/listautocomplete/{mmId}/{roleId}', name: 'pumukit_ldap_person_listautocomplete', methods: ['GET'])]
    #[Template('@PumukitLDAP/Person/listautocomplete.html.twig')]
    public function listautocompleteAction(#[MapDocument(id: 'mmId')] MultimediaObject $multimediaObject, #[MapDocument(id: 'roleId')] Role $role): array
    {
        $this->checkEnabled();

        return [
            'mm' => $multimediaObject,
            'role' => $role,
            'template' => $multimediaObject->isPrototype() ? '_template' : '',
        ];
    }

    #[Route('/autocomplete', name: 'pumukit_ldap_person_autocomplete', methods: ['GET'])]
    public function autocompleteAction(Request $request): Response
    {
        $this->checkEnabled();

        $term = trim($request->query->getString('term'));
        if (mb_strlen($term) < 2) {
            return new JsonResponse([]);
        }

        try {
            $people = $this->ldapService->searchUsers($term);
        } catch (\Throwable $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(array_map(static fn (array $person): array => ['mail' => $person['mail'], 'cn' => $person['cn']], $people));
    }

    #[Route('/link/{mmId}/{roleId}', name: 'pumukit_ldap_person_link', methods: ['POST'])]
    public function linkAction(Request $request, #[MapDocument(id: 'mmId')] MultimediaObject $multimediaObject, #[MapDocument(id: 'roleId')] Role $role): Response
    {
        $this->checkEnabled();

        $personalScopeRoleCode = $this->personService->getPersonalScopeRoleCode();
        if ($role->getCod() === $personalScopeRoleCode) {
            $this->denyAccessUnlessGranted(Permission::ADD_OWNER);
        }

        $mail = trim($request->request->getString('mail'));
        if ('' === $mail) {
            return new Response('Missing email', Response::HTTP_BAD_REQUEST);
        }

        try {
            $person = $this->personService->findPersonByEmail($mail) ?? $this->createPersonFromLDAP($mail);
            $multimediaObject = $this->personService->createRelationPerson($person, $role, $multimediaObject);
        } catch (\Throwable $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->render('@PumukitNewAdmin/Person/listrelation.html.twig', [
            'people' => $multimediaObject->getPeopleByRole($role, true),
            'role' => $role,
            'personal_scope_role_code' => $personalScopeRoleCode,
            'mm' => $multimediaObject,
            'template' => $multimediaObject->isPrototype() ? '_template' : '',
        ]);
    }

    private function createPersonFromLDAP(string $mail): Person
    {
        $users = $this->ldapService->getListUsers('', $mail);
        if (!$users) {
            throw new \InvalidArgumentException(sprintf('There is no LDAP user with the email "%s"', $mail));
        }

        $person = new Person();
        $person->setName($users[0]['cn']);
        $person->setEmail($users[0]['mail']);
        $this->personService->savePerson($person);
        $this->logger->info('Person created from LDAP', ['email' => $users[0]['mail']]);

        return $person;
    }

    private function checkEnabled(): void
    {
        if (!$this->ldapService->isConfigured()) {
            throw new NotFoundHttpException();
        }
    }
}
