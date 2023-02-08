<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckConnectionCommand extends ContainerAwareCommand
{
    private $ldapService;

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

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->ldapService = $this->getContainer()->get('pumukit_ldap.ldap');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $result = $this->ldapService->checkConnection();

        if (!$result) {
            $output->writeln('Cannot connect with LDAP');
        } else {
            $output->writeln($result);
        }

        return 0;
    }
}
