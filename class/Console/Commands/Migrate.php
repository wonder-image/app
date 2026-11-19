<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Migrations\Migrator;
use Wonder\Console\Commands\Concerns\BootsWonderApp;

/**
 * `forge migrate [--pretend] [--force] [--check]`
 *
 * Applica le migrazioni versionate in attesa. Le distruttive, fuori da `local`,
 * partono solo con `--force`. `--check` non tocca il database: esce con codice
 * diverso da 0 se un file è cambiato dopo l'applicazione o è sparito.
 */
class Migrate extends Command
{
    use BootsWonderApp;

    public $name = 'migrate';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Applica le migrazioni versionate in attesa')
            ->addOption('pretend', null, InputOption::VALUE_NONE, 'Stampa l\'SQL senza eseguire')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ammette le migrazioni distruttive fuori da local')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Verifica i file senza eseguire (per la CI)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->bootApp($output)) {
            return Command::FAILURE;
        }

        $migrator = new Migrator(\Wonder\Sql\Connection::Connect('main'));

        if ($input->getOption('check')) {
            $problems = $migrator->check();

            foreach ($problems as $problem) {
                $output->writeln('<error>❌ '.$problem.'</error>');
            }

            return $problems === [] ? Command::SUCCESS : Command::FAILURE;
        }

        $result = $migrator->runPending([
            'pretend' => (bool) $input->getOption('pretend'),
            'force' => (bool) $input->getOption('force'),
        ]);

        foreach ($result->applied as $row) {
            $output->writeln(($input->getOption('pretend') ? '○ ' : '✔ ').$row['source'].' · '.$row['id']);

            foreach ($input->getOption('pretend') ? $row['statements'] : [] as $sql) {
                $output->writeln('    '.$sql);
            }
        }

        foreach ($result->skipped as $row) {
            $output->writeln('<comment>⏭  '.$row['id'].' è distruttiva: serve --force fuori da local.</comment>');
        }

        if ($result->status === 'busy') {
            $output->writeln('<comment>'.$result->error.'</comment>');
            return Command::SUCCESS;
        }

        if (!$result->success) {
            $output->writeln('<error>❌ '.$result->error.'</error>');
            return Command::FAILURE;
        }

        if ($result->applied === [] && $result->skipped === []) {
            $output->writeln('Nessuna migrazione in attesa.');
        }

        return $result->skipped === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
