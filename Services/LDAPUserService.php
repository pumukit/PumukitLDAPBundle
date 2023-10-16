<?php

namespace Pumukit\LDAPBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\GroupService;
use Pumukit\SchemaBundle\Services\PermissionProfileService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LDAPUserService
{
    public const EDU_PERSON_AFFILIATION = 'edupersonaffiliation';
    public const IRISCLASSIFCODE = 'irisclassifcode';
    public const ORIGIN = 'ldap';

    protected $dm;
    protected $userService;
    protected $ldapService;
    protected $permissionProfile;
    protected $logger;
    protected $personService;
    protected $permissionProfileService;
    protected $groupService;

    public function __construct(
        DocumentManager $documentManager,
        UserService $userService,
        PersonService $personService,
        LDAPService $LDAPService,
        PermissionProfileService $permissionProfile,
        GroupService $groupService,
        LoggerInterface $logger
    ) {
        $this->dm = $documentManager;
        $this->userService = $userService;
        $this->personService = $personService;
        $this->ldapService = $LDAPService;
        $this->permissionProfileService = $permissionProfile;
        $this->groupService = $groupService;
        $this->logger = $logger;
    }

    public function createUser(array $info, ?string $username)
    {
        if (!isset($username)) {
            throw new \InvalidArgumentException('Uid is not set ');
        }

        $user = $this->dm->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            try {
                $user = $this->newUser($info, $username);
            } catch (\Exception $e) {
                throw new AuthenticationException($e->getMessage());
            }
        } elseif ($info['mail'][0] !== $user->getEmail() || $info['cn'][0] !== $user->getFullname()) {
            try {
                $user = $this->updateUser($info, $user);
            } catch (\Exception $e) {
                throw new AuthenticationException($e->getMessage());
            }
        }
        $this->updateGroups($info, $user);
        $this->promoteUser($info, $user);

        return $user;
    }

    public function getEmail(array $info): string
    {
        if (isset($info['mail'][0])) {
            return $info['mail'][0];
        }

        throw new AuthenticationException('Missing LDAP attribute email');
    }

    protected function newUser(array $info, string $username): User
    {
        $email = $this->getEmail($info);

        $user = $this->dm->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
        } else {
            throw new AuthenticationException('Duplicated email key');
        }

        if (isset($info['mail'][0])) {
            $user->setEmail($info['mail'][0]);
        }

        $user->setUsername($username);

        if (isset($info['cn'][0])) {
            $user->setFullname($info['cn'][0]);
        }

        $permissionProfile = $this->permissionProfileService->getByName('Viewer');
        $user->setPermissionProfile($permissionProfile);
        $user->setOrigin(self::ORIGIN);
        $user->setEnabled(true);

        $this->userService->create($user);
        $this->personService->referencePersonIntoUser($user);

        return $user;
    }

    protected function getGroup(string $key, ?string $type = null): Group
    {
        $cleanKey = $this->getGroupKey($key, $type);
        $cleanName = $this->getGroupName($key, $type);

        $group = $this->dm->getRepository(Group::class)->findOneBy(['key' => $cleanKey]);
        if ($group) {
            return $group;
        }
        $group = new Group();
        $group->setKey($cleanKey);
        $group->setName($cleanName);
        $group->setOrigin(self::ORIGIN);
        $this->groupService->create($group);

        return $group;
    }

    protected function getGroupKey(string $key, ?string $type = null)
    {
        return preg_replace('/\W/', '', $key);
    }

    protected function getGroupName(string $key, ?string $type = null)
    {
        return $key;
    }

    protected function promoteUser(array $info, User $user)
    {
        $permissionProfileAutoPub = $this->permissionProfileService->getByName('Auto Publisher');
        $permissionProfileAdmin = $this->permissionProfileService->getByName('Administrator');
        $permissionProfileIngestor = $this->permissionProfileService->getByName('Ingestor');
        $permissionProfilePublisher = $this->permissionProfileService->getByName('Publisher');
        $permissionProfileViewer = $this->permissionProfileService->getByName('Viewer');

        if ($this->isAutoPub($info, $user->getUsername())) {
            $user->setPermissionProfile($permissionProfileAutoPub);
            $this->userService->update($user, true, false);
        }

        if ($this->isAdmin($info, $user->getUsername())) {
            $user->setPermissionProfile($permissionProfileAdmin);
            $this->userService->update($user, true, false);
        }

        if ($this->isIngestor($info, $user->getUsername())) {
            $user->setPermissionProfile($permissionProfileIngestor);
            $this->userService->update($user, true, false);
        }

        if ($this->isPublisher($info, $user->getUsername())) {
            $user->setPermissionProfile($permissionProfilePublisher);
            $this->userService->update($user, true, false);
        }

        if ($this->isViewer($info, $user->getUsername())) {
            $user->setPermissionProfile($permissionProfileViewer);
            $this->userService->update($user, true, false);
        }
    }

    protected function updateGroups(array $info, User $user)
    {
        $aGroups = [];
        if (isset($info[self::EDU_PERSON_AFFILIATION][0])) {
            foreach ($info[self::EDU_PERSON_AFFILIATION] as $key => $value) {
                if ('count' !== $key) {
                    try {
                        $group = $this->getGroup($value, self::EDU_PERSON_AFFILIATION);
                        $this->userService->addGroup($group, $user, true, false);
                        $aGroups[] = $group->getKey();
                        $this->logger->info(self::class.' ['.__FUNCTION__.'] Added Group: '.$group->getName());
                    } catch (\ErrorException $e) {
                        $this->logger->info(
                            self::class.' ['.__FUNCTION__.'] Invalid Group '.$value.': '.$e->getMessage()
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            self::class.' ['.__FUNCTION__.'] Error on adding Group '.$value.': '.$e->getMessage()
                        );
                    }
                }
            }
        }

        if (isset($info[self::IRISCLASSIFCODE][0])) {
            foreach ($info[self::IRISCLASSIFCODE] as $key => $value) {
                if ('count' !== $key) {
                    try {
                        $group = $this->getGroup($value, self::IRISCLASSIFCODE);
                        $this->userService->addGroup($group, $user, true, false);
                        $aGroups[] = $group->getKey();
                        $this->logger->info(self::class.' ['.__FUNCTION__.'] Added Group: '.$group->getName());
                    } catch (\ErrorException $e) {
                        $this->logger->info(
                            self::class.' ['.__FUNCTION__.'] Invalid Group '.$value.': '.$e->getMessage()
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            self::class.' ['.__FUNCTION__.'] Error on adding Group '.$value.': '.$e->getMessage()
                        );
                    }
                }
            }
        }

        foreach ($user->getGroups() as $group) {
            if (self::ORIGIN === $group->getOrigin()) {
                if (!in_array($group->getKey(), $aGroups)) {
                    try {
                        $this->userService->deleteGroup($group, $user, true, false);
                    } catch (\Exception $e) {
                        $this->logger->error(self::class.' ['.__FUNCTION__.'] Delete group '.$group->getKey().' from user  : '.$e->getMessage());
                    }
                }
            }
        }

        return $user;
    }

    protected function updateUser(array $info, User $user)
    {
        if (isset($info['mail'][0])) {
            $user->setEmail($info['mail'][0]);
        }

        if (isset($info['cn'][0])) {
            $user->setFullname($info['cn'][0]);
        }

        $this->userService->update($user, true, false);

        return $user;
    }

    protected function isAutoPub(array $info, string $username): bool
    {
        return false;
    }

    protected function isAdmin(array $info, string $username): bool
    {
        return false;
    }

    protected function isIngestor(array $info, string $username): bool
    {
        return false;
    }

    protected function isPublisher(array $info, string $username): bool
    {
        return false;
    }

    protected function isViewer(array $info, string $username): bool
    {
        return false;
    }
}
