<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\LDAPBundle\Services\LDAPService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Services\PersonService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/ldap/person")
 *
 * @Security("is_granted('ROLE_ACCESS_MULTIMEDIA_SERIES')")
 */
class PersonController extends AbstractController
{
    private $documentManager;
    private $LDAPService;
    private $personService;

    public function __construct(DocumentManager $documentManager, LDAPService $LDAPService, PersonService $personService)
    {
        $this->documentManager = $documentManager;
        $this->LDAPService = $LDAPService;
        $this->personService = $personService;
    }

    /**
     * @Route("/button", name="pumukit_ldap_person_button")
     *
     * @ParamConverter("multimediaObject", options={"id" = "mmId"})
     * @ParamConverter("role", options={"id" = "roleId"})
     *
     * @Template("@PumukitLDAP/Person/button.html.twig")
     */
    public function buttonAction(Request $request, MultimediaObject $multimediaObject, Role $role): array
    {
        $ldapConnected = $this->LDAPService->checkConnection();

        return [
            'ldap_connected' => $ldapConnected,
            'mm' => $multimediaObject,
            'role' => $role,
        ];
    }

    /**
     * @Route("/listautocomplete/{mmId}/{roleId}", name="pumukit_ldap_person_listautocomplete")
     *
     * @ParamConverter("multimediaObject", options={"id" = "mmId"})
     * @ParamConverter("role", options={"id" = "roleId"})
     *
     * @Template("@PumukitLDAP/Person/listautocomplete.html.twig")
     */
    public function listautocompleteAction(MultimediaObject $multimediaObject, Role $role): array
    {
        $template = $multimediaObject->isPrototype() ? '_template' : '';

        return [
            'mm' => $multimediaObject,
            'role' => $role,
            'template' => $template,
        ];
    }

    /**
     * @Route("/autocomplete", name="pumukit_ldap_person_autocomplete")
     */
    public function autocompleteAction(Request $request)
    {
        $login = $request->get('term');
        $out = [];

        try {
            $people = $this->LDAPService->getListUsers('*'.$login.'*', '*'.$login.'*');
            foreach ($people as $person) {
                $out[] = [
                    'value' => $person['cn'],
                    'label' => $person['cn'],
                    'mail' => $person['mail'],
                    'cn' => $person['cn'],
                ];
            }
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 400);
        }

        return new JsonResponse($out);
    }

    /**
     * @Route("/link/{mmId}/{roleId}", name="pumukit_ldap_person_link")
     *
     * @ParamConverter("multimediaObject", options={"id" = "mmId"})
     * @ParamConverter("role", options={"id" = "roleId"})
     */
    public function linkAction(Request $request, MultimediaObject $multimediaObject, Role $role): Response
    {
        $cn = $request->get('cn');
        $email = $request->get('mail');
        $personalScopeRoleCode = $this->personService->getPersonalScopeRoleCode();

        try {
            $person = $this->personService->findPersonByEmail($email);
            if (null === $person) {
                $person = $this->createPersonFromLDAP($cn, $email);
            }
            $multimediaObject = $this->personService->createRelationPerson($person, $role, $multimediaObject);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 400);
        }
        $template = $multimediaObject->isPrototype() ? '_template' : '';

        return $this->render(
            '@PumukitNewAdmin/Person/listrelation.html.twig',
            [
                'people' => $multimediaObject->getPeopleByRole($role, true),
                'role' => $role,
                'personal_scope_role_code' => $personalScopeRoleCode,
                'mm' => $multimediaObject,
                'template' => $template,
            ]
        );
    }

    private function createPersonFromLDAP(string $cn = '', string $mail = ''): Person
    {
        try {
            $aux = $this->LDAPService->getListUsers('', $mail);
            if (0 === count($aux)) {
                throw new \InvalidArgumentException('There is no LDAP user with the name "'.$cn.'" and email "'.$mail.'"');
            }
            $person = new Person();
            $person->setName($aux[0]['cn']);
            $person->setEmail($aux[0]['mail']);
            $this->documentManager->persist($person);
            $this->documentManager->flush();
        } catch (\Exception $e) {
            throw $e;
        }

        return $person;
    }
}
