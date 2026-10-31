<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Event;

use Pumukit\SchemaBundle\Document\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class LdapLoginEvent extends Event
{
    public function __construct(
        private readonly User $user,
        private readonly array $entry,
        private readonly Request $request,
        private readonly TokenInterface $token,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEntry(): array
    {
        return $this->entry;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    public function getDn(): ?string
    {
        return $this->entry['dn'] ?? null;
    }

    public function getValue(string $attribute): ?string
    {
        return $this->getValues($attribute)[0] ?? null;
    }

    public function getValues(string $attribute): array
    {
        $values = $this->entry[strtolower($attribute)] ?? [];
        if (!is_array($values)) {
            return [];
        }
        unset($values['count']);

        return array_values(array_map('strval', $values));
    }
}
