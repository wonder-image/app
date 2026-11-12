<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wonder\App\Media\ResizeQueue;

/**
 * `forge media:resize [--failed]`
 *
 * Svuota la coda dei ridimensionamenti senza aspettare lo scheduler. Con
 * `--failed` rimette prima in coda quelli falliti. Le righe in attesa di un
 * nuovo tentativo restano al loro orario.
 */
class MediaResize extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('media:resize')
            ->setDescription('Genera le misure delle immagini in coda')
            ->addOption('failed', null, InputOption::VALUE_NONE, 'Rimette in coda anche i ridimensionamenti falliti');
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

        $total = ['ready' => 0, 'retried' => 0, 'failed' => 0, 'missing' => 0];

        try {
            $store = ResizeQueue::store();

            if (!$store->usable()) {
                $output->writeln('<comment>⚠️  Tabella image_resizes assente: eseguire prima forge update.</comment>');
                return Command::FAILURE;
            }

            if ($input->getOption('failed')) {
                $output->writeln('Rimessi in coda: '.$store->retryFailed(ResizeQueue::time(time())));
            }

            do {
                $result = ResizeQueue::drain(null, null);

                if (!empty($result['busy'])) {
                    $output->writeln('<comment>⚠️  Un altro processo sta già lavorando la coda.</comment>');
                    return Command::FAILURE;
                }

                $done = 0;
                foreach ($total as $key => $_) {
                    $total[$key] += (int) ($result[$key] ?? 0);
                    $done += (int) ($result[$key] ?? 0);
                }
            } while ($done > 0);
        } catch (Throwable $throwable) {
            $output->writeln('<error>❌ Ridimensionamento non riuscito: '.$throwable->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>✅ Pronte: %d, da riprovare: %d, fallite: %d, file mancanti: %d</info>',
            $total['ready'], $total['retried'], $total['failed'], $total['missing']
        ));

        return $total['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
