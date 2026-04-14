<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Provision extends Config
{
    public $name = 'provision';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Configura Bitwarden e GitHub per il progetto locale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->isCiEnvironment()) {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: php forge provision è pensato solo per il locale.</comment>');
            return Command::SUCCESS;
        }

        $cwd = getcwd() ?: '.';
        $envPath = $cwd.'/.env';
        $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return Command::FAILURE;
        }

        Dotenv::createImmutable($cwd)->safeLoad();

        if (!$this->ensureCommandInstalled('bws', ['bws', '--version'], $output, false)) {
            return Command::FAILURE;
        }

        if (!$this->ensureCommandInstalled('gh', ['gh', '--version'], $output)) {
            return Command::FAILURE;
        }

        $keyToIndex = $this->envKeyToIndex($lines);
        $updatedKeys = [];

        $appDomain = $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));
        if ($appDomain === '') {
            $appDomain = $this->defaultAppDomain($cwd);

            if ($appDomain === '') {
                $appDomain = $this->askRequiredDomain($input, $output);
            }

            if ($appDomain === '') {
                return Command::FAILURE;
            }

            $this->setEnvValue($lines, $keyToIndex, 'APP_DOMAIN', $appDomain);
            $updatedKeys[] = 'APP_DOMAIN';
        }

        $appUrl = $this->buildAppUrl($appDomain);
        if ($this->envValue($lines, $keyToIndex, 'APP_URL') !== $appUrl) {
            $this->setEnvValue($lines, $keyToIndex, 'APP_URL', $appUrl);
            $updatedKeys[] = 'APP_URL';
        }

        $assetsVersion = $this->envValue($lines, $keyToIndex, 'ASSETS_VERSION');
        if ($assetsVersion === '') {
            $assetsVersion = '0.0';
            $this->setEnvValue($lines, $keyToIndex, 'ASSETS_VERSION', $assetsVersion);
            $updatedKeys[] = 'ASSETS_VERSION';
        }

        $bwAccessToken = $this->envValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN');
        if ($bwAccessToken === '') {
            $bwAccessToken = $this->askRequiredValue($input, $output, 'BWS_ACCESS_TOKEN', 'Inserisci BWS_ACCESS_TOKEN Bitwarden:', true);

            if ($bwAccessToken === '') {
                return Command::FAILURE;
            }

            $this->setEnvValue($lines, $keyToIndex, 'BWS_ACCESS_TOKEN', $bwAccessToken);
            $updatedKeys[] = 'BWS_ACCESS_TOKEN';
        }

        if (count($updatedKeys) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);

            $safeKeys = array_map(
                fn ($key) => $key === 'BWS_ACCESS_TOKEN' ? 'BWS_ACCESS_TOKEN=***' : $key,
                $updatedKeys
            );

            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: '.implode(', ', $safeKeys).'</info>');
        }

        $bwProjectId = $this->envValue($lines, $keyToIndex, 'BWS_PROJECT_ID');
        if ($bwProjectId === '') {
            $bwProjectId = $this->ensureBitwardenProjectId($appDomain, $bwAccessToken, $output);

            if ($bwProjectId === '') {
                return Command::FAILURE;
            }

            $this->setEnvValue($lines, $keyToIndex, 'BWS_PROJECT_ID', $bwProjectId);
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
            $output->writeln('<info>✅ Configurazione Bitwarden aggiornata nel file .env: BWS_PROJECT_ID</info>');
        }

        if (!$this->ensureGithubRepository($appDomain, $output)) {
            return Command::FAILURE;
        }

        if (!$this->syncGithubRepositorySecrets($appDomain, [
            'BWS_ACCESS_TOKEN' => $bwAccessToken,
            'BWS_PROJECT_ID' => $bwProjectId,
        ], $output)) {
            return Command::FAILURE;
        }

        if (!$this->syncGithubRepositoryVariables($appDomain, [
            'APP_DOMAIN' => $appDomain,
            'ASSETS_VERSION' => $assetsVersion,
        ], $output)) {
            return Command::FAILURE;
        }

        if (!$this->ensureBitwardenProjectEnvValues($input, $output, $envPath, $lines, $keyToIndex)) {
            return Command::FAILURE;
        }

        if (!$this->syncBitwardenProjectSecrets($bwProjectId, $bwAccessToken, $lines, $keyToIndex, $output)) {
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Provisioning GitHub e Bitwarden completato.</info>');

        return Command::SUCCESS;
    }
}
