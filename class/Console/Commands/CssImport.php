<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Support\CssConfigSync;

/**
 * `forge css:import [file]`
 *
 * Importa una configurazione CSS da un file JSON (generato da
 * `forge css:export`) e rigenera i file `root.css` e `color.css`.
 *
 * Le tabelle singleton (`css_default`, `css_input`, `css_modal`,
 * `css_dropdown`, `css_alert`) vengono aggiornate in-place (riga id=1).
 * Le tabelle multi-row (`css_font`, `css_color`) vengono svuotate e
 * ripopolate.
 *
 * Pensato per essere usato:
 * - dopo `forge start` in locale (per allinearsi alla produzione)
 * - manualmente per sincronizzare ambienti
 * - nel pipeline `forge update` (automatico via `build/update/css.php`)
 */
class CssImport extends Command
{
    public $name = 'css:import';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Importa la configurazione CSS da un file JSON e rigenera root.css e color.css.')
            ->addArgument('file', InputArgument::OPTIONAL, 'Percorso del file JSON da importare', CssConfigSync::CONFIG_PATH)
            ->addOption('no-rebuild', null, InputOption::VALUE_NONE, 'Non rigenerare i file CSS dopo l\'importazione');
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

        if (!file_exists($file)) {
            $output->writeln('<error>❌ File non trovato: '.$file.'</error>');
            return Command::FAILURE;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            $output->writeln('<error>❌ Impossibile leggere '.$file.'</error>');
            return Command::FAILURE;
        }

        $config = json_decode($json, true);

        if (!is_array($config)) {
            $output->writeln('<error>❌ Il file JSON non è valido.</error>');
            return Command::FAILURE;
        }

        $imported = CssConfigSync::importConfig($config);

        if (!$imported) {
            $output->writeln('<error>❌ Nessuna tabella importata.</error>');
            return Command::FAILURE;
        }

        foreach (CssConfigSync::ALL_TABLES as $table) {
            if (!isset($config[$table]) || !is_array($config[$table])) {
                $output->writeln('<comment>⚠️  '.$table.': non presente nel JSON</comment>');
                continue;
            }

            $count = count($config[$table]);
            $label = $count === 1 ? '1 riga' : $count.' righe';
            $output->writeln('   '.$table.': '.$label.' importate');
        }

        if (!$input->getOption('no-rebuild')) {
            cssRoot();
            cssColor();
            $output->writeln('<info>✅ File CSS rigenerati (root.css, color.css).</info>');
        }

        $output->writeln('<info>✅ Importazione completata da '.$file.'</info>');

        return Command::SUCCESS;
    }
}
