<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\EventListener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Pumukit\LDAPBundle\Event\LdapLoginEvent;
use Pumukit\LDAPBundle\Services\LDAPService;
use Pumukit\SchemaBundle\Document\User;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[WithMonologChannel('ldap')]
class LoginListener
{
    public function __construct(
        private readonly LDAPService $ldapService,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly bool $onLoginEnabled = false,
        private readonly string $onLoginLdapAttribute = 'uid',
        private readonly string $onLoginUserField = 'username',
    ) {}

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        if (!$this->onLoginEnabled || !$this->ldapService->isConfigured()) {
            return;
        }

        $token = $event->getAuthenticationToken();
        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $value = 'email' === $this->onLoginUserField ? $user->getEmail() : $user->getUsername();
        if (null === $value || '' === $value) {
            return;
        }

        try {
            $entry = $this->ldapService->getInfoFrom($this->onLoginLdapAttribute, $value);
        } catch (\Throwable $exception) {
            $this->logger->error('LDAP lookup on login failed', ['user' => $user->getUsername(), 'error' => $exception->getMessage()]);

            return;
        }

        if (false === $entry) {
            $this->logger->info('User not found in LDAP on login', ['user' => $user->getUsername(), $this->onLoginLdapAttribute => $value]);

            return;
        }

        $this->dispatcher->dispatch(new LdapLoginEvent($user, $entry, $event->getRequest(), $token));
    }
}
