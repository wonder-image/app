<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scarica nel `.env` locale le credenziali di sviluppo condivise dal
 * project Bitwarden Secrets Manager `dev-shared`.
 */
class BitwardenCredentials extends Config
{
    public $name = 'credentials';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setAliases(['bitwarden:pull'])
            ->setDescription('Scarica da Bitwarden le credenziali dev-shared nel .env locale')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Sovrascrive gli override locali con i valori dev-shared (le chiavi project-specific restano escluse)'
            )
            ->addOption(
                'refresh-token',
                null,
                InputOption::VALUE_NONE,
                'Ignora BWS_ACCESS_TOKEN corrente, chiede un nuovo token e lo salva solo dopo averlo validato'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->isCiEnvironment()) {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: php forge credentials è pensato solo per il locale.</comment>');
            return Command::SUCCESS;
        }

        if (!$this->ensureCommandInstalled('bws', self::REQUIRED_COMMAND_VERSION_COMMANDS['bws'], $output, false)) {
            return Command::FAILURE;
        }

        $cwd = getcwd() ?: '.';
        $envPath = $cwd.'/.env';
        $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return Command::FAILURE;
        }

        $runtimeToken = getenv('BWS_ACCESS_TOKEN');
        if (!is_string($runtimeToken) || trim($runtimeToken) === '') {
            $runtimeToken = $_ENV['BWS_ACCESS_TOKEN'] ?? '';
        }
        $runtimeToken = is_string($runtimeToken) ? trim($runtimeToken) : '';

        Dotenv::createImmutable($cwd)->safeLoad();
        \Wonder\App\EnvCompat::apply();

        $keyToIndex = $this->envKeyToIndex($lines);
        $storedToken = $this->envValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN');
        $persistToken = false;

        if ((bool) $input->getOption('refresh-token')) {
            $bwAccessToken = '';
        } elseif ($runtimeToken !== '') {
            $bwAccessToken = $runtimeToken;
            $persistToken = $runtimeToken !== $storedToken;
        } else {
            $bwAccessToken = $storedToken;
        }

        if ($bwAccessToken === '') {
            $bwAccessToken = $this->askRequiredValue(
                $input,
                $output,
                'BWS_ACCESS_TOKEN',
                'Inserisci BWS_ACCESS_TOKEN Bitwarden:',
                true
            );

            if ($bwAccessToken === '') {
                return Command::FAILURE;
            }
            $persistToken = true;
        }

        $projectId = $this->resolveDevSharedProjectId($bwAccessToken, $output);

        if ($projectId === null) {
            $output->writeln('<error>❌ Project Bitwarden "dev-shared" non trovato.</error>');
            $output->writeln('<comment>   Verifica che BWS_ACCESS_TOKEN abbia accesso al project oppure imposta BWS_DEV_SHARED_PROJECT_ID nel .env.</comment>');
            $output->writeln('<comment>   Se il token è scaduto o revocato, rilancia `php forge credentials --refresh-token`.</comment>');
            return Command::FAILURE;
        }

        $secrets = $this->bitwardenProjectSecretsWithValues($projectId, $bwAccessToken, $output);

        if ($secrets === null) {
            $output->writeln('<comment>   Se il token è scaduto o revocato, rilancia `php forge credentials --refresh-token`.</comment>');
            return Command::FAILURE;
        }

        if ($persistToken) {
            $this->setQuotedEnvValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN', $bwAccessToken);

            if (file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL) === false) {
                $output->writeln('<error>❌ Impossibile salvare BWS_ACCESS_TOKEN nel file .env</error>');
                return Command::FAILURE;
            }

            $_ENV['BWS_ACCESS_TOKEN'] = $bwAccessToken;
            putenv('BWS_ACCESS_TOKEN='.$bwAccessToken);

            $output->writeln('<info>✅ Salvato BWS_ACCESS_TOKEN validato nel .env locale</info>');
        }

        $merged = $this->mergeDevSharedSecretsIntoLocalEnv(
            $secrets,
            $envPath,
            $lines,
            $keyToIndex,
            $output,
            (bool) $input->getOption('force')
        );

        if ($merged === null) {
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Credenziali Bitwarden dev-shared sincronizzate nel .env locale.</info>');

        return Command::SUCCESS;
    }
}
