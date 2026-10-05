<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\LibVersion;
use Wonder\App\UpdateRunner;

class Update extends Command
{
    public $name = 'update';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Esegue update applicativo e task CLI locali opzionali')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Esegue anche i file di build/cli');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $bootstrap = $root.'/vendor/wonder-image/app/wonder-image.php';

        if (!file_exists($bootstrap)) {
            $output->writeln('<error>❌ Bootstrap wonder-image non trovato.</error>');
            return Command::FAILURE;
        }

        $lib = LibVersion::check($root);

        if ($lib !== null) {
            $output->writeln("<error>❌ wonder-image (lib) {$lib['installed']} è più vecchia della versione minima richiesta: {$lib['minimum']}.</error>");
            $output->writeln('<comment>   Aggiorna la lib e rilancia il comando:</comment>');
            $output->writeln("<comment>   {$lib['command']}</comment>");
            return Command::FAILURE;
        }

        $ROOT = $root;
        $GLOBALS['ROOT'] = $root;

        require_once $bootstrap;

        $runner = new UpdateRunner();
        $result = $runner->execute([
            'trigger_type' => 'cli',
            'source' => 'local',
            'include_cli_files' => (bool) $input->getOption('local'),
        ]);

        $output->writeln($runner->jsonPayload($result));

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }
}
