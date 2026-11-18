<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Support\SyncPlan;

/**
 * `forge sync:from-production --dry-run`
 *
 * Scheletro del comando di sincronizzazione produzione → staging: oggi sa solo
 * fare la prova a secco. Rifiuta di partire se `APP_ENV` non è `staging` e
 * fallisce se una tabella non è classificata (`SyncClassification`). Non apre
 * alcuna connessione alla produzione e non copia niente.
 */
class SyncFromProduction extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('sync:from-production')
            ->setDescription('Prova a secco della sincronizzazione produzione → staging (la copia non è ancora implementata).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mostra cosa verrebbe copiato, senza toccare nulla (unica modalità disponibile).');
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

        if (($refusal = SyncPlan::refusal()) !== null) {
            $output->writeln('<error>❌ '.$refusal.'</error>');
            return Command::FAILURE;
        }

        if (!$input->getOption('dry-run')) {
            $output->writeln('<error>❌ La copia non è ancora implementata: usa --dry-run.</error>');
            return Command::FAILURE;
        }

        $plan = SyncPlan::build();

        if ($plan['blocking'] !== []) {
            $output->writeln('<error>❌ Classificazione incompleta, nessuna tabella verrebbe copiata:</error>');
            foreach ($plan['blocking'] as $problem) {
                $output->writeln('   - '.$problem);
            }
            return Command::FAILURE;
        }

        $output->writeln('<info>Prova a secco: nessun dato letto o scritto.</info>');
        $output->writeln('Verrebbero copiate ('.count($plan['copy']).'): '.implode(', ', $plan['copy']));
        $output->writeln('Dal codice, non copiate ('.count($plan['skipCode']).'): '.implode(', ', $plan['skipCode']));
        $output->writeln('Ambiente, mai toccate ('.count($plan['skipEnvironment']).'): '.implode(', ', $plan['skipEnvironment']));
        $output->writeln('<comment>Dopo la copia: migrazioni in attesa (punto 5, non ancora agganciato), poi dati di codice.</comment>');

        return Command::SUCCESS;
    }
}
