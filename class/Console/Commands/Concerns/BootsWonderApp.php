<?php

namespace Wonder\Console\Commands\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait BootsWonderApp
{
    /** Carica il bootstrap del sito; false (con messaggio) se non c'è. */
    protected function bootApp(OutputInterface $output): bool
    {
        $root = getcwd() ?: '.';
        $bootstrap = $root.'/vendor/wonder-image/app/wonder-image.php';

        if (!file_exists($bootstrap)) {
            $output->writeln('<error>❌ Bootstrap wonder-image non trovato.</error>');
            return false;
        }

        $ROOT = $root;
        $GLOBALS['ROOT'] = $root;

        require_once $bootstrap;

        return true;
    }
}
