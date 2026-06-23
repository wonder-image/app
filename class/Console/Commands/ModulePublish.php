<?php // class/Console/Commands/ModulePublish.php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Discovery;

class ModulePublish extends Command
{
    protected static $defaultName = 'module:publish';

    protected function configure(): void
    {
        $this
            ->setName('module:publish')
            ->setDescription('Pubblica le view di un modulo negli override del sito (custom/view/...)')
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug del modulo (es. rsvp)')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Sottocartella o file relativo a view/ (es. components o components/form.php)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Sovrascrive i file già esistenti');
    }

    protected function destinationFor(string $relative, string $slug, string $root): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $slash = strpos($relative, '/');

        if ($slash === false) {
            // file in root di view/: custom/view/{slug}/<file>
            return $root.'/custom/view/'.$slug.'/'.$relative;
        }

        $topDir = substr($relative, 0, $slash);
        $rest = substr($relative, $slash + 1);

        return $root.'/custom/view/'.$topDir.'/'.$slug.'/'.$rest;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $autoload = $root.'/vendor/autoload.php';

        if (!is_file($autoload)) {
            $output->writeln('<error>❌ Autoload consumer non trovato.</error>');
            return Command::FAILURE;
        }

        require_once $autoload;
        LegacyGlobals::share(['ROOT' => $root]);

        $slug = (string) $input->getArgument('slug');
        $manifest = null;

        foreach (Discovery::discover() as $candidate) {
            if ($candidate->slug() === $slug) {
                $manifest = $candidate;
                break;
            }
        }

        if ($manifest === null) {
            $output->writeln("<error>❌ Modulo '{$slug}' non trovato.</error>");
            return Command::FAILURE;
        }

        $viewsDir = $manifest->viewsPath();

        if ($viewsDir === null || !is_dir($viewsDir)) {
            $output->writeln("<error>❌ Il modulo '{$slug}' non ha una cartella view/.</error>");
            return Command::FAILURE;
        }

        $viewsDir = rtrim($viewsDir, '/');
        $only = trim((string) $input->getOption('only'), '/');
        $force = (bool) $input->getOption('force');

        $sourcePath = $only !== '' ? $viewsDir.'/'.$only : $viewsDir;

        if (!file_exists($sourcePath)) {
            $output->writeln("<error>❌ '{$only}' non esiste in view/ del modulo.</error>");
            return Command::FAILURE;
        }

        $files = is_file($sourcePath)
            ? [$sourcePath]
            : $this->collectFiles($sourcePath);

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $relative = ltrim(substr($file, strlen($viewsDir)), '/');
            $dest = $this->destinationFor($relative, $slug, $root);

            if (is_file($dest) && !$force) {
                $output->writeln("  <comment>– saltato</comment> {$relative}");
                $skipped++;
                continue;
            }

            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            if (!copy($file, $dest)) {
                $output->writeln("  <error>✗ errore</error> {$relative}");
                $failed++;
                continue;
            }

            $output->writeln("  <info>✓ copiato</info> {$relative}");
            $copied++;
        }

        $output->writeln("\n{$copied} copiati, {$skipped} saltati, {$failed} falliti.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return string[] */
    private function collectFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                $out[] = $file->getPathname();
            }
        }

        sort($out);

        return $out;
    }
}
