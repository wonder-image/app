<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Support\TableSync;

/**
 * `forge export [file]`
 *
 * Esporta tutte le tabelle sincronizzabili (quelle il cui Model
 * dichiara `syncSchema()`) in un file JSON committabile in git.
 *
 * Il file generato e pensato per essere importato con `forge import`
 * su qualunque ambiente, garantendo coerenza tra locale, staging
 * e produzione.
 *
 * Quali tabelle vengono esportate dipende da:
 * 1. Override via `TableSync::setSyncTables()` (dal bootstrap del sito)
 * 2. Env `SYNC_TABLES` (comma-separated)
 * 3. Default: tutte le tabelle con `syncSchema()` non-null
 */
class Export extends Command
{
    public $name = 'export';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Esporta le tabelle sincronizzabili in un file JSON committabile in git.')
            ->addArgument('file', InputArgument::OPTIONAL, 'Percorso del file di output', TableSync::CONFIG_PATH);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $bootstrap = $root.'/vendor/wonder-image/app/wonder-image.php';

        if (!file_exists($bootstrap)) {
            $output->writeln('<error>❌ Bootstrap wonder-image non trovato.</error>');
            return Command::FAILURE;
        }

        $GLOBALS['ROOT'] = $root;
        require_once $bootstrap;

        $file = $input->getArgument('file');

        if (!str_starts_with($file, '/')) {
            $file = $root.'/'.$file;
        }

        $discovered = TableSync::discoverTables();

        if ($discovered === []) {
            $output->writeln('<comment>⚠️  Nessuna tabella sincronizzabile trovata.</comment>');
            return Command::SUCCESS;
        }

        $config = TableSync::exportConfig();

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (file_put_contents($file, $json."\n") === false) {
            $output->writeln('<error>❌ Impossibile scrivere '.$file.'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Dati esportati in '.$file.'</info>');

        foreach (TableSync::syncTables() as $table) {
            $count = isset($config[$table]) ? count($config[$table]) : 0;
            $label = $count === 1 ? '1 riga' : $count.' righe';
            $output->writeln('   '.$table.': '.$label);
        }

        $skipped = array_diff(array_keys($discovered), TableSync::syncTables());

        if ($skipped !== []) {
            $output->writeln('<comment>   ⊘ Escluse dal sync: '.implode(', ', $skipped).'</comment>');
        }

        return Command::SUCCESS;
    }
}
