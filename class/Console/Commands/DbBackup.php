<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wonder\App\Support\DatabaseBackup;

/**
 * `forge db:backup [--keep=N] [--path=DIR]`
 *
 * Dump gzip del database in `storage/backup/<Ymd-His>.sql.gz`,
 * mantenendo gli ultimi N backup (default 5).
 */
class DbBackup extends Command
{
    public $name = 'db:backup';

    /** Percorso di mysqldump; null = cerca nel PATH, false = solo dump PHP. */
    protected string|false|null $mysqldumpBinary = null;

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Crea un backup gzip del database in storage/backup')
            ->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Numero di backup da mantenere', (string) DatabaseBackup::DEFAULT_KEEP)
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Cartella di destinazione alternativa');
    }

    protected function makeBackup(): DatabaseBackup
    {
        return new DatabaseBackup($this->mysqldumpBinary);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $bootstrap = $root.'/vendor/wonder-image/app/wonder-image.php';

        if (!file_exists($bootstrap)) {
            $output->writeln('<error>❌ Bootstrap wonder-image non trovato.</error>');
            return Command::FAILURE;
        }

        $keep = filter_var($input->getOption('keep'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($keep === false) {
            $output->writeln('<error>❌ --keep deve essere un intero maggiore o uguale a 1.</error>');
            return Command::FAILURE;
        }

        $path = trim((string) ($input->getOption('path') ?? ''));

        if ($path !== '' && !str_starts_with($path, '/')) {
            $path = $root.'/'.$path;
        }

        $GLOBALS['ROOT'] = $root;
        require_once $bootstrap;

        try {
            $backup = $this->makeBackup()->run($path !== '' ? $path : DatabaseBackup::defaultDirectory($root), $keep);
        } catch (Throwable $throwable) {
            $output->writeln('<error>❌ Backup non riuscito: '.$throwable->getMessage().'</error>');
            return Command::FAILURE;
        }

        if ($backup->fallback !== null) {
            $output->writeln('<comment>⚠️  mysqldump non riuscito, usato il dump PHP: '.$backup->fallback.'</comment>');
        }

        $output->writeln('<info>✅ Backup creato: '.$backup->path.' ('.$backup->method.', '.number_format($backup->bytes / 1024, 1).' KB)</info>');

        foreach ($backup->removed as $removed) {
            $output->writeln('<comment>   Rimosso backup vecchio: '.$removed.'</comment>');
        }

        return Command::SUCCESS;
    }
}
