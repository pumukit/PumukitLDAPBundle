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

    public function __construct($server, $bindRdn, $bindPassword, $baseDn, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->bindRdn = $bindRdn;
        $this->bindPassword = $bindPassword;
        $this->baseDn = $baseDn;
        $this->logger = $logger;
    }

    public function isConfigured(): bool
    {
        return (bool)$this->server;
    }

    public function checkConnection()
    {
        if (!$this->isConfigured()) {
            throw new \Exception('LDAP is not configured');
        }
        $linkIdentifier = false;

        try {
            $linkIdentifier = $this->createConnection();
            $this->addDebugOptions($linkIdentifier);
            $this->addOptions($linkIdentifier);

            if ($linkIdentifier) {
                $result = $this->bindConnection($linkIdentifier);
                ldap_close($linkIdentifier);

                return $result;
            }
        } catch (\Exception $e) {
            return ldap_error($linkIdentifier);
        }

        return false;
    }

    /**
     * Is user Checks if user with given password exists in LDAP Server.
     */
    public function isUser(string $user = '', string $pass = ''): bool
    {
        if ('' === $pass) {
            return false;
        }
        $ret = false;

        try {
            $linkIdentifier = $this->createConnection();
            $this->addOptions($linkIdentifier);
            if ($linkIdentifier) {
                $this->bindConnection($linkIdentifier);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != $info['count'])) {
                        $dn = $info[0]['dn'];
                        $ret = @ldap_bind($linkIdentifier, $dn, $pass);
                    }
                }
                $this->closeConnection($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $ret;
    }

    public function getName(string $user)
    {
        $name = false;

        try {
            $linkIdentifier = $this->createConnection();
            $this->addOptions($linkIdentifier);
            if ($linkIdentifier) {
                $this->bindConnection($linkIdentifier);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != count($info))) {
                        $name = $info[0]['cn'][0];
                    }
                }
                $this->closeConnection($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $name;
    }

    public function getMail(string $user)
    {
        $name = false;

        try {
            $linkIdentifier = $this->createConnection();
            $this->addOptions($linkIdentifier);
            if ($linkIdentifier) {
                $this->bindConnection($linkIdentifier);
                $searchResult = ldap_search($linkIdentifier, $this->baseDn, 'uid='.$user, [], 0, 1);
                if ($searchResult) {
                    $info = ldap_get_entries($linkIdentifier, $searchResult);
                    if (($info) && (0 != count($info))) {
                        $name = $info[0]['mail'][0];
                    }
                }
                $this->closeConnection($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $name;
    }

    public function getInfoFromEmail(string $email)
    {
        return $this->getInfoFrom('mail', $email);
    }

    public function getInfoFrom(string $key, string $value)
    {
        $return = false;

        $linkIdentifier = $this->createConnection();
        $this->addOptions($linkIdentifier);
        if ($linkIdentifier) {
            $this->bindConnection($linkIdentifier);
            $searchResult = ldap_search($linkIdentifier, $this->baseDn, $key.'='.$value, [], 0, 1);
            if ($searchResult) {
                $info = ldap_get_entries($linkIdentifier, $searchResult);
                $this->logger->info('***** Getting user from LDAP: '. $value);
                $this->logger->info(json_encode($info));
                if (($info) && (0 != count($info)) && isset($info[0])) {
                    $return = $info[0];
                    $this->logger->info('----- Info: ' . $return);
                }
            }

            $this->closeConnection($linkIdentifier);
        }

        return $return;
    }

    /**
     * Searches LDAP users by CN or MAIL
     * If CN is an empty string or null and MAIL a given string:
     * - Returns LDAP users with given MAIL
     * If MAIL is an empty string or null and CN a given string:
     * - Returns LDAP users with given CN
     * If CN and MAIL are strings (equal or different):
     * - Returns LDAP users with given CN and LDAP users with given MAIL
     */
    public function getListUsers(string $cn = '', string $mail = ''): array
    {
        $limit = 40;
        $out = [];

        try {
            $linkIdentifier = $this->createConnection();
            $this->addOptions($linkIdentifier);
            if ($linkIdentifier) {
                $this->bindConnection($linkIdentifier);
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
                $this->closeConnection($linkIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger->error(__CLASS__.' ['.__FUNCTION__.'] '.$e->getMessage());

            throw $e;
        }

        return $out;
    }

    public function createConnection()
    {
        return ldap_connect($this->server);
    }

    public function bindConnection($linkIdentifier): bool
    {
        return @ldap_bind($linkIdentifier, $this->bindRdn, $this->bindPassword);
    }

    public function addOptions($linkIdentifier)
    {
        ldap_set_option($linkIdentifier, LDAP_OPT_PROTOCOL_VERSION, 3);
    }

    public function addDebugOptions($linkIdentifier)
    {
        ldap_set_option($linkIdentifier, LDAP_OPT_DEBUG_LEVEL, 7);
    }

    public function closeConnection($linkIdentifier)
    {
        ldap_close($linkIdentifier);
    }

    /**
     * Get filter.
     *
     * Builds LDAP filter by CN or MAIL
     * If CN is an empty string or null and MAIL a given string:
     * - Returns LDAP query with given MAIL
     * If MAIL is an empty string or null and CN a given string:
     * - Returns LDAP query with given CN
     * If CN and MAIL are strings (equal or different):
     * - Returns LDAP query with given CN and LDAP users with given MAIL
     */
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
