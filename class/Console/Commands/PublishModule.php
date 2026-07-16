<?php

namespace Wonder\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Credentials;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\Assets;
use Wonder\App\Module\Manifest;
use Wonder\App\Module\ManifestValidator;
use Wonder\App\Module\Registry;

class PublishModule extends Command
{
    public $name = 'publish:module';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Pubblica le view di un modulo in custom/modules/<slug>/view (con --assets pubblica gli asset in assets/{ASSETS_VERSION})')
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug del modulo')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path relativo dentro paths.views (o paths.assets con --assets) da pubblicare')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sovrascrive i file gia pubblicati')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mostra le operazioni senza scrivere file')
            ->addOption('list', null, InputOption::VALUE_NONE, 'Mostra i file publishabili senza scrivere file')
            ->addOption('assets', null, InputOption::VALUE_NONE, 'Pubblica gli asset (paths.assets) in assets/{ASSETS_VERSION} del sito')
            ->addOption('views', null, InputOption::VALUE_NONE, 'Pubblica tutte le view (default)')
            ->addOption('components', null, InputOption::VALUE_NONE, 'Pubblica solo view/components/*')
            ->addOption('layouts', null, InputOption::VALUE_NONE, 'Pubblica solo view/layout/*')
            ->addOption('pages', null, InputOption::VALUE_NONE, 'Pubblica solo view/pages/*');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';
        $autoload = $root.'/vendor/autoload.php';

        if (!is_file($autoload)) {
            $output->writeln('<error>Autoload consumer non trovato.</error>');
            return Command::FAILURE;
        }

        require_once $autoload;
        LegacyGlobals::share(['ROOT' => $root]);

        $slug = trim((string) $input->getArgument('slug'));
        $manifest = $this->findManifest($slug);

        if ($manifest === null) {
            $output->writeln('<error>Modulo non trovato: '.$slug.'</error>');
            return Command::FAILURE;
        }

        ManifestValidator::assertValid($manifest);

        $publishAssets = (bool) $input->getOption('assets');

        if ($publishAssets) {
            $sourceRoot = $manifest->assetsPath();
            if ($sourceRoot === null || !is_dir($sourceRoot)) {
                $output->writeln('<error>Il modulo '.$slug.' non dichiara una cartella asset pubblicabile.</error>');
                return Command::FAILURE;
            }

            // ASSETS_VERSION arriva dall'env del sito (in console non è definita).
            Credentials::loadEnv();

            $targetRoot = Assets::publishTarget($root);
        } else {
            $sourceRoot = $manifest->viewsPath();
            if ($sourceRoot === null || !is_dir($sourceRoot)) {
                $output->writeln('<error>Il modulo '.$slug.' non dichiara una cartella view pubblicabile.</error>');
                return Command::FAILURE;
            }

            $targetRoot = rtrim($root, '/').'/custom/modules/'.$manifest->slug().'/view';
        }

        $sourceRoot = rtrim((string) realpath($sourceRoot), '/');

        $files = $this->publishableFiles($sourceRoot, $input);

        if ($files === []) {
            $output->writeln('<comment>Nessuna view pubblicabile trovata per '.$manifest->slug().'.</comment>');
            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('list')) {
            foreach ($files as $relativePath) {
                $output->writeln($relativePath);
            }

            return Command::SUCCESS;
        }

        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
        $published = 0;
        $skipped = 0;

        foreach ($files as $relativePath) {
            $source = $sourceRoot.'/'.$relativePath;
            $target = $targetRoot.'/'.$relativePath;

            if (file_exists($target) && !$force) {
                $skipped++;
                $output->writeln('<comment>Skip '.$relativePath.' (esiste gia; usa --force per sovrascrivere)</comment>');
                continue;
            }

            if ($dryRun) {
                $published++;
                $output->writeln(($force ? 'overwrite ' : 'publish ').$relativePath.' -> '.$target);
                continue;
            }

            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                $output->writeln('<error>Impossibile creare '.$targetDir.'</error>');
                return Command::FAILURE;
            }

            if (!copy($source, $target)) {
                $output->writeln('<error>Impossibile pubblicare '.$relativePath.'</error>');
                return Command::FAILURE;
            }

            $published++;
            $output->writeln('<info>Pubblicato '.$relativePath.'</info>');
        }

        $output->writeln(json_encode([
            'success' => true,
            'slug' => $manifest->slug(),
            'target' => $targetRoot,
            'published' => $published,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function findManifest(string $slug): ?Manifest
    {
        try {
            return Registry::get($slug);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function publishableFiles(string $sourceRoot, InputInterface $input): array
    {
        $requestedPath = trim((string) ($input->getArgument('path') ?? ''), '/');

        if ($requestedPath !== '') {
            return $this->filesForRequestedPath($sourceRoot, $requestedPath);
        }

        $prefixes = $this->selectedPrefixes($input);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = $this->relativePath($sourceRoot, $file->getPathname());

            if ($relativePath === null || !$this->matchesPrefixes($relativePath, $prefixes)) {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function filesForRequestedPath(string $sourceRoot, string $requestedPath): array
    {
        $candidate = $sourceRoot.'/'.$requestedPath;
        $realCandidate = realpath($candidate);

        if (!is_string($realCandidate) || !$this->isInside($realCandidate, $sourceRoot)) {
            return [];
        }

        if (is_file($realCandidate)) {
            $relativePath = $this->relativePath($sourceRoot, $realCandidate);
            return $relativePath !== null ? [$relativePath] : [];
        }

        if (!is_dir($realCandidate)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realCandidate, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = $this->relativePath($sourceRoot, $file->getPathname());

            if ($relativePath !== null) {
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function selectedPrefixes(InputInterface $input): array
    {
        // I filtri per prefisso riguardano solo le view.
        if ((bool) $input->getOption('assets')) {
            return [];
        }

        $prefixes = [];

        if ((bool) $input->getOption('components')) {
            $prefixes[] = 'components/';
        }

        if ((bool) $input->getOption('layouts')) {
            $prefixes[] = 'layout/';
        }

        if ((bool) $input->getOption('pages')) {
            $prefixes[] = 'pages/';
        }

        return $prefixes;
    }

    /**
     * @param array<int, string> $prefixes
     */
    private function matchesPrefixes(string $relativePath, array $prefixes): bool
    {
        if ($prefixes === []) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $sourceRoot, string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');

        if (!$this->isInside($path, $sourceRoot)) {
            return null;
        }

        return ltrim(substr($path, strlen($sourceRoot)), '/');
    }

    private function isInside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }
}
