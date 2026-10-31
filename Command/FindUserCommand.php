<?php

declare(strict_types=1);

namespace Pumukit\LDAPBundle\Command;

use Pumukit\LDAPBundle\Services\LDAPService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pumukit:ldap:find:user', description: 'Show the LDAP entry of a user, as PuMuKIT receives it')]
class FindUserCommand extends Command
{
    private const HIDDEN = '******';

    public function __construct(private readonly LDAPService $ldapService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('value', InputArgument::REQUIRED, 'Value to search for')
            ->addOption('attribute', 'a', InputOption::VALUE_REQUIRED, 'LDAP attribute to search by', 'uid')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the entry as JSON')
            ->setHelp(
                <<<'EOT'
                    Searches the LDAP entry whose attribute matches the value and prints every attribute.
                    Password attributes are hidden.

                    Examples:
                        php bin/console pumukit:ldap:find:user jdoe
                        php bin/console pumukit:ldap:find:user jdoe@example.org -a mail
                        php bin/console pumukit:ldap:find:user jdoe --json
                    EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $attribute = (string) $input->getOption('attribute');
        $value = (string) $input->getArgument('value');

        try {
            $entry = $this->ldapService->getInfoFrom($attribute, $value);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (false === $entry) {
            $io->warning(sprintf('No LDAP entry with %s=%s', $attribute, $value));

            return Command::FAILURE;
        }

        $attributes = $this->attributes($entry);

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode(['dn' => $entry['dn'] ?? null] + $attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

            return Command::SUCCESS;
        }

        $io->section((string) ($entry['dn'] ?? ''));
        $io->table(['Attribute', 'Values'], array_map(
            static fn (string $name, array $values): array => [$name, implode("\n", $values)],
            array_keys($attributes),
            $attributes
        ));

        return Command::SUCCESS;
    }

    private function attributes(array $entry): array
    {
        $attributes = [];
        foreach ($entry as $name => $values) {
            if (!is_string($name) || 'dn' === $name || 'count' === $name || !is_array($values)) {
                continue;
            }
            unset($values['count']);
            $values = array_values($values);
            if (preg_match('/password|pwd/i', $name)) {
                $values = array_fill(0, count($values), self::HIDDEN);
            } else {
                $values = array_map(static fn ($value): string => mb_check_encoding((string) $value, 'UTF-8') ? (string) $value : '(binary, '.strlen((string) $value).' bytes)', $values);
            }
            $attributes[$name] = $values;
        }
        ksort($attributes);

        return $attributes;
    }
}
