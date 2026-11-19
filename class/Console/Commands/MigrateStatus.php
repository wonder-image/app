<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Migrations\Migrator;
use Wonder\Console\Commands\Concerns\BootsWonderApp;

/** `forge migrate:status [--json]`: applicata | in attesa | modificata | mancante. */
class MigrateStatus extends Command
{
    use BootsWonderApp;

    public $name = 'migrate:status';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Mostra lo stato delle migrazioni versionate')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Esito in JSON (lo legge il deploy)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->bootApp($output)) {
            return Command::FAILURE;
        }

        $rows = (new Migrator(\Wonder\Sql\Connection::Connect('main')))->status();

        if ($input->getOption('json')) {
            $output->writeln(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $labels = ['applied' => 'applicata', 'pending' => 'in attesa', 'modified' => 'modificata', 'missing' => 'mancante'];

        foreach ($rows as $row) {
            $output->writeln(sprintf('%-10s %-10s %s', $labels[$row['state']] ?? $row['state'], $row['source'], $row['id']));
        }

        if ($rows === []) {
            $output->writeln('Nessuna migrazione.');
        }

        return Command::SUCCESS;
    }
}
