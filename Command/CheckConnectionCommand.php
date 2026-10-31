<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Command;

use Pumukit\LDAPBundle\Services\LDAPService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pumukit:ldap:check:connection', description: 'Check the connection and the bind to the LDAP server')]
class CheckConnectionCommand extends Command
{
    public function __construct(
        private readonly LDAPService $ldapService,
        private readonly string $server,
        private readonly ?string $bindRdn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'EOT'
                Connects to the configured LDAP server and binds with bind_rdn (or anonymously).

                Example:
                    php bin/console pumukit:ldap:check:connection
                EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->definitionList(
            ['Server' => $this->server],
            ['Bind' => $this->bindRdn ?? 'anonymous'],
        );

        try {
            $this->ldapService->checkConnection();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Connected to the LDAP server');

        return Command::SUCCESS;
    }
}
