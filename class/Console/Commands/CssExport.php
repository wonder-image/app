<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Support\CssConfigSync;

/**
 * `forge css:export [file]`
 *
 * Esporta la configurazione CSS (colori, font, tipografia, input, modal,
 * dropdown, alert) in un file JSON che può essere committato in git come
 * single source of truth per il design system.
 *
 * Il file generato è pensato per essere importato con `forge css:import`
 * su qualunque ambiente, garantendo coerenza grafica tra locale, staging
 * e produzione.
 */
class CssExport extends Command
{
    public $name = 'css:export';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Esporta la configurazione CSS in un file JSON committabile in git.')
            ->addArgument('file', InputArgument::OPTIONAL, 'Percorso del file di output', 'css-config.json');
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

        $config = CssConfigSync::exportConfig();

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($file, $json."\n") === false) {
            $output->writeln('<error>❌ Impossibile scrivere '.$file.'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Configurazione CSS esportata in '.$file.'</info>');

        foreach (CssConfigSync::ALL_TABLES as $table) {
            $count = count($config[$table]);
            $label = $count === 1 ? '1 riga' : $count.' righe';
            $output->writeln('   '.$table.': '.$label);
        }

        return Command::SUCCESS;
    }
}
