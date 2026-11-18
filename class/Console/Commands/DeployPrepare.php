<?php

namespace Wonder\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\App\Deploy\ReleaseManifest;

/**
 * `forge deploy:prepare`
 *
 * Prepara una release dalla cartella corrente (in CI, dopo `composer install`):
 * esegue `forge build` e scrive `deploy-manifest.json` per ultimo.
 *
 * Non si connette al database, non carica il bootstrap e non tocca nessun sito
 * in servizio: lavora solo sulla cartella dove viene lanciato. Se si interrompe
 * prima del manifesto, `forge deploy:activate` rifiuta la cartella.
 * Punto d'aggancio: i passi di build aggiunti dal punto 4 (nginx) vanno chiamati
 * in `runBuild()`, prima della scrittura del manifesto.
 */
class DeployPrepare extends Command
{
    public $name = 'deploy:prepare';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Prepara una release (build + manifesto) senza toccare il sito in servizio')
            ->addOption('release', null, InputOption::VALUE_REQUIRED, 'Identificativo della release (default: GITHUB_SHA, altrimenti local-<data>)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Passa --force a forge build')
            ->addOption('force-www', null, InputOption::VALUE_NONE, 'Passa --force-www a forge build')
            ->addOption('skip-build', null, InputOption::VALUE_NONE, 'Non esegue forge build (già eseguito)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';

        $release = (string) ($input->getOption('release') ?: getenv('GITHUB_SHA') ?: 'local-'.gmdate('YmdHis'));

        if (!ReleaseManifest::validId($release)) {
            $output->writeln('<error>❌ Identificativo di release non valido (lettere, cifre, punto, trattino; massimo 100 caratteri).</error>');
            return Command::FAILURE;
        }

        foreach (['vendor/autoload.php', 'vendor/wonder-image/app/wonder-image.php'] as $required) {
            if (!is_file($root.'/'.$required)) {
                $output->writeln("<error>❌ Manca {$required}: esegui prima composer install.</error>");
                return Command::FAILURE;
            }
        }

        if (is_file($root.'/.env')) {
            $output->writeln('<comment>⚠️  .env presente nella release: con il deploy da riga di comando le variabili arrivano dall\'ambiente, non dal file.</comment>');
        }

        if (!$input->getOption('skip-build')) {
            $code = $this->runBuild($output, (bool) $input->getOption('force'), (bool) $input->getOption('force-www'));

            if ($code !== Command::SUCCESS) {
                $output->writeln('<error>❌ forge build non riuscito: manifesto non scritto, la release non è attivabile.</error>');
                return Command::FAILURE;
            }
        }

        if (!is_file($root.'/handler/index.php')) {
            $output->writeln('<error>❌ Manca handler/index.php: la build non ha prodotto il front controller.</error>');
            return Command::FAILURE;
        }

        try {
            $manifest = ReleaseManifest::write($root, $release, [
                'appVersion' => $this->appVersion(),
                'php' => PHP_VERSION,
            ]);
        } catch (RuntimeException $e) {
            $output->writeln('<error>❌ '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Release '.$manifest['release'].' pronta ('.ReleaseManifest::FILE.').</info>');

        return Command::SUCCESS;
    }

    /** Esegue `forge build` (sostituibile nei test). */
    protected function runBuild(OutputInterface $output, bool $force, bool $forceWww): int
    {
        $args = ['command' => 'build'];

        if ($force) {
            $args['--force'] = true;
        }
        if ($forceWww) {
            $args['--force-www'] = true;
        }

        return $this->getApplication()->find('build')->run(new ArrayInput($args), $output);
    }

    protected function appVersion(): ?string
    {
        return class_exists(\Wonder\App\Version::class) ? (string) \Wonder\App\Version::get() : null;
    }
}
