<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Command;

use Pumukit\LDAPBundle\Services\LDAPService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckConnectionCommand extends Command
{
    private $ldapService;

    public function __construct(LDAPService $ldapService)
    {
        $this->ldapService = $ldapService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('pumukit:ldap:check:connection')
            ->setDescription('Check connection to LDAP.')
            ->setHelp(
                <<<'EOT'
This command use parameters values from LDAP to check the connection.

Example:
    pumukit:ldap:check:connection
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        try {
            $result = $this->ldapService->checkConnection();
            $output->writeln($result);
        } catch (\Exception $exception) {
            $output->writeln('Cannot connect with LDAP');
        }

        return 0;
    }
}
