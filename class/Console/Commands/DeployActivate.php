<?php

namespace Wonder\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Deploy\ReleaseManifest;
use Wonder\App\Deploy\ReleaseSwitcher;
use Wonder\App\LibVersion;
use Wonder\App\UpdateRunner;

/**
 * `forge deploy:activate`
 *
 * Attiva una release preparata con `forge deploy:prepare`:
 *  1. legge e valida `deploy-manifest.json` (senza, rifiuta);
 *  2. esegue l'update (backup, migrazioni, build/row, build/update) con il codice della release,
 *     usando `release_id` del manifesto: una release già applicata risulta `skipped`;
 *  3. solo se l'update è riuscito (o già applicato) sposta il link `current` in modo atomico.
 *
 * Se l'update fallisce o un altro update è in corso, il link non si sposta e il sito
 * continua a servire la release precedente. Rilanciare il comando è sicuro.
 * `--rollback` sposta il link su una release precedente senza toccare il database
 * (le migrazioni "prima aggiungi, poi rimuovi" lo rendono compatibile).
 *
 * Punti d'aggancio (altri punti della Fase 2): pulizia di opcache/reload PHP-FPM e
 * cache condivise (storage e sessioni in `shared/`) a carico dell'infrastruttura.
 */
class DeployActivate extends Command
{
    public $name = 'deploy:activate';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Attiva una release preparata: update, poi spostamento del link current')
            ->addOption('release-dir', null, InputOption::VALUE_REQUIRED, 'Cartella della release (default: cartella corrente)')
            ->addOption('current', null, InputOption::VALUE_REQUIRED, 'Link da spostare (default: <padre di releases>/current)')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Salta il backup del database')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Torna alla release precedente (solo link, nessun update)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Con --rollback: release di destinazione')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Controlla manifesto e link senza eseguire nulla');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rollback = (bool) $input->getOption('rollback');
        $dry = (bool) $input->getOption('dry-run');

        try {
            $releaseDir = $this->resolveReleaseDir($input, $rollback);
            $linkOption = $input->getOption('current');
            $link = $linkOption ? (string) $linkOption : dirname(dirname($releaseDir)).'/current';
            $switcher = new ReleaseSwitcher($link);

            if ($rollback) {
                $releaseDir = $switcher->rollbackTarget($input->getOption('to') ? (string) $input->getOption('to') : null);
            }

            $manifest = ReleaseManifest::read($releaseDir);
        } catch (RuntimeException $e) {
            $output->writeln('<error>❌ '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $release = (string) $manifest['release'];

        if ($dry) {
            $output->writeln($this->json(['status' => 'dry-run', 'release' => $release, 'dir' => $releaseDir, 'link' => $switcher->link(), 'rollback' => $rollback]));
            return Command::SUCCESS;
        }

        $update = null;

        if (!$rollback) {
            $update = $this->runUpdate($releaseDir, $release, !$input->getOption('no-backup'), $output);

            if (!$update['success']) {
                $output->writeln($this->json(['status' => $update['status'], 'release' => $release, 'switched' => false, 'message' => $update['message']]));
                $output->writeln('<error>❌ Update non riuscito: il link current non è stato spostato.</error>');
                return Command::FAILURE;
            }
        }

        try {
            $switch = $switcher->activate($releaseDir);
        } catch (RuntimeException $e) {
            $output->writeln('<error>❌ '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln($this->json([
            'status' => $rollback ? 'rolled-back' : 'activated',
            'release' => $switch['release'],
            'previous' => $switch['previous'],
            'update' => $update['status'] ?? null,
            'switched' => true,
        ]));

        return Command::SUCCESS;
    }

    private function resolveReleaseDir(InputInterface $input, bool $rollback): string
    {
        $dir = $input->getOption('release-dir') ? (string) $input->getOption('release-dir') : (getcwd() ?: '.');
        $real = realpath($dir);

        if ($real === false || !is_dir($real)) {
            throw new RuntimeException('Cartella della release non trovata: '.$dir);
        }

        if (!$input->getOption('current') && basename(dirname($real)) !== 'releases' && !$rollback) {
            throw new RuntimeException('La release deve stare in <sito>/releases/<id>, oppure indica il link con --current.');
        }

        return $real;
    }

    /**
     * Esegue l'update con il codice della release (sostituibile nei test).
     *
     * @return array{success: bool, status: string, message: string}
     */
    protected function runUpdate(string $releaseDir, string $release, bool $backup, OutputInterface $output): array
    {
        $bootstrap = $releaseDir.'/vendor/wonder-image/app/wonder-image.php';

        if (!file_exists($bootstrap)) {
            return ['success' => false, 'status' => 'failed', 'message' => 'Bootstrap wonder-image non trovato nella release.'];
        }

        // Il codice dell'update deve essere quello della release: se forge è stato lanciato da un'altra
        // release (es. current/forge), UpdateRunner è già caricato da lì e le migrazioni sarebbero quelle vecchie.
        $loaded = (string) (new \ReflectionClass(UpdateRunner::class))->getFileName();

        if (!str_starts_with($loaded, rtrim($releaseDir, '/').'/')) {
            return ['success' => false, 'status' => 'failed', 'message' => 'forge non è quello della release: lancia '.$releaseDir.'/forge deploy:activate (o php forge dalla cartella della release).'];
        }

        chdir($releaseDir);

        $lib = LibVersion::check($releaseDir);

        if ($lib !== null) {
            return ['success' => false, 'status' => 'failed', 'message' => "wonder-image (lib) {$lib['installed']} è più vecchia della versione minima richiesta: {$lib['minimum']}."];
        }

        $ROOT = $releaseDir;
        $GLOBALS['ROOT'] = $releaseDir;

        require_once $bootstrap;

        $runner = new UpdateRunner();
        $result = $runner->execute([
            'release_id' => $release,
            'trigger_type' => 'cli',
            'source' => 'deploy',
            'include_cli_files' => false,
            'backup' => $backup,
        ]);

        return ['success' => (bool) $result->success, 'status' => (string) $result->status, 'message' => (string) $result->message];
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }
}
