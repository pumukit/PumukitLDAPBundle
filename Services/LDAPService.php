<?php

namespace Pumukit\LDAPBundle\Services;

use Psr\Log\LoggerInterface;

class LDAPService
{
    private $server;
    private $bindRdn;
    private $bindPassword;
    private $baseDn;
    private $logger;

    public function __construct(
        string $server,
        string $bindRdn,
        string $bindPassword,
        string $baseDn,
        LoggerInterface $logger
    ) {
        $this->server = $server;
        $this->bindRdn = $bindRdn;
        $this->bindPassword = $bindPassword;
        $this->baseDn = $baseDn;
        $this->logger = $logger;
    }

    public function isConfigured(): bool
    {
        return ($this->server) ? true : false;
    }

    public function checkConnection(): bool
    {
        if ($this->isConfigured()) {
            try {
                $linkIdentifier = ldap_connect($this->server);
                ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
                if ($linkIdentifier) {
                    $result = ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
                    ldap_close($linkIdentifier);

                    return $result;
                }
            } catch (\Exception $e) {
                $this->logger->debug(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

                return false;
            }
        }

        return false;
    }

    public function isUser(string $user = '', string $pass = ''): bool
    {
        if ('' === $pass) {
            return false;
        }
        $ret = false;

        try {
            $linkIdentifier = ldap_connect($this->server);
            ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($linkIdentifier) {
                ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != $info['count'])) {
                        $dn = $info[0]['dn'];
                        $ret = @ldap_bind($linkIdentifier, $dn, $pass);
                    }
                }
                ldap_close($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $ret;
    }

    public function getName(string $user): string
    {
        $name = false;

        try {
            $linkIdentifier = ldap_connect($this->server);
            ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($linkIdentifier) {
                ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != count($info))) {
                        $name = $info[0]['cn'][0];
                    }
                }
                ldap_close($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $name;
    }

    public function getMail(string $user): string
    {
        $name = false;

        try {
            $linkIdentifier = ldap_connect($this->server);
            ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($linkIdentifier) {
                ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != count($info))) {
                        $name = $info[0]['mail'][0];
                    }
                }
                ldap_close($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $name;
    }

    public function getInfoFromEmail($email)
    {
        return $this->getInfoFrom('mail', $email);
    }

    public function getInfoFrom(string $key, string $value)
    {
        $return = false;

        $linkIdentifier = ldap_connect($this->server);
        ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
        if ($linkIdentifier) {
            ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
            $searchResult = ldap_search($linkIdentifier, $this->baseDn, $key.'='.$value, [], 0, 1);
            if ($searchResult) {
                $info = ldap_get_entries($linkIdentifier, $searchResult);
                if (($info) && (0 != count($info)) && isset($info[0])) {
                    $return = $info[0];
                }
            }
            ldap_close($linkIdentifier);
        }

        return $return;
    }

    public function getListUsers(string $cn = '', string $mail = ''): array
    {
        $limit = 40;
        $out = [];

        try {
            $linkIdentifier = ldap_connect($this->server);
            ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($linkIdentifier) {
                ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
                $filter = $this->getFilter($cn, $mail);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, $filter, [], 0, $limit);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != count($info))) {
                        foreach ($info as $k => $i) {
                            if ('count' === $k) {
                                continue;
                            }
                            $out[] = [
                                'mail' => $i['mail'][0],
                                'cn' => $i['cn'][0],
                            ];
                        }
                    }
                }
                ldap_close($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $out;
    }

    private function getFilter(string $cn = '', string $mail = ''): string
    {
        $filter = ($cn ? 'cn='.$cn : '');
        if ($mail) {
            if ($filter) {
                $filter = '(|('.$filter.')(mail='.$mail.'))';
            } else {
                $filter = 'mail='.$mail;
            }
        }

        return $filter;
    }
}
