<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Services;

use LDAP\Connection;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('ldap')]
class LDAPService
{
    public const LIST_LIMIT = 40;
    private const NETWORK_TIMEOUT = 5;

    public function __construct(
        private readonly string $server,
        private readonly string $baseDn,
        private readonly LoggerInterface $logger,
        private readonly ?string $bindRdn = null,
        private readonly ?string $bindPassword = null,
        private readonly int $version = 3,
        private readonly bool $startTls = false,
    ) {}

    public function isConfigured(): bool
    {
        return '' !== $this->server;
    }

    public function checkConnection(): bool
    {
        $connection = $this->connect();
        ldap_unbind($connection);

        return true;
    }

    public function getInfoFrom(string $key, string $value): array|false
    {
        $entries = $this->search($this->equals($key, $value), 1);

        return $entries[0] ?? false;
    }

    public function getInfoFromEmail(string $email): array|false
    {
        return $this->getInfoFrom('mail', $email);
    }

    public function getName(string $user): ?string
    {
        $info = $this->getInfoFrom('uid', $user);

        return $info['cn'][0] ?? null;
    }

    public function getMail(string $user): ?string
    {
        $info = $this->getInfoFrom('uid', $user);

        return $info['mail'][0] ?? null;
    }

    public function getListUsers(string $cn = '', string $mail = ''): array
    {
        $filters = [];
        if ('' !== $cn) {
            $filters[] = $this->equals('cn', $cn);
        }
        if ('' !== $mail) {
            $filters[] = $this->equals('mail', $mail);
        }

        return $this->listUsers($filters);
    }

    public function searchUsers(string $term): array
    {
        $term = trim($term);
        if ('' === $term) {
            return [];
        }
        $escaped = ldap_escape($term, '', LDAP_ESCAPE_FILTER);

        return $this->listUsers(['(cn=*'.$escaped.'*)', '(mail=*'.$escaped.'*)']);
    }

    private function listUsers(array $filters): array
    {
        if (!$filters) {
            return [];
        }
        $filter = 1 === count($filters) ? $filters[0] : '(|'.implode('', $filters).')';

        $users = [];
        foreach ($this->search($filter, self::LIST_LIMIT, ['cn', 'mail']) as $entry) {
            if (!isset($entry['mail'][0])) {
                continue;
            }
            $users[] = [
                'mail' => $entry['mail'][0],
                'cn' => $entry['cn'][0] ?? $entry['mail'][0],
            ];
        }

        return $users;
    }

    private function equals(string $key, string $value): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $key)) {
            throw new \InvalidArgumentException(sprintf('Invalid LDAP attribute "%s"', $key));
        }

        return '('.$key.'='.ldap_escape($value, '', LDAP_ESCAPE_FILTER).')';
    }

    private function search(string $filter, int $limit, array $attributes = []): array
    {
        $connection = $this->connect();

        try {
            $result = @ldap_search($connection, $this->baseDn, $filter, $attributes, 0, $limit);
            if (false === $result) {
                $error = ldap_error($connection);
                $this->logger->error('LDAP search failed', ['filter' => $filter, 'error' => $error]);

                throw new \RuntimeException('LDAP search failed: '.$error);
            }

            $entries = ldap_get_entries($connection, $result);
            if (false === $entries) {
                return [];
            }
            unset($entries['count']);

            return array_values($entries);
        } finally {
            ldap_unbind($connection);
        }
    }

    private function connect(): Connection
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('The LDAP server is not configured');
        }

        $connection = @ldap_connect($this->server);
        if (false === $connection) {
            throw new \RuntimeException(sprintf('Invalid LDAP server URI "%s"', $this->server));
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $this->version);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, self::NETWORK_TIMEOUT);

        if ($this->startTls && !@ldap_start_tls($connection)) {
            return $this->fail($connection, 'LDAP StartTLS failed');
        }

        $bound = null === $this->bindRdn ? @ldap_bind($connection) : @ldap_bind($connection, $this->bindRdn, (string) $this->bindPassword);
        if (!$bound) {
            return $this->fail($connection, 'LDAP bind failed');
        }

        return $connection;
    }

    private function fail(Connection $connection, string $message): never
    {
        $error = ldap_error($connection);
        ldap_unbind($connection);
        $this->logger->error($message, ['server' => $this->server, 'bind_rdn' => $this->bindRdn, 'error' => $error]);

        throw new \RuntimeException($message.': '.$error);
    }
}
