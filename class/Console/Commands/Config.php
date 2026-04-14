<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Dotenv\Dotenv;


class Config extends Command
{
    public $name = 'config';

    protected function configure(): void
    {
        $this->setName($this->name);
        $this->setDescription('Configura il progetto per locale o CI e installa wonder-image lato NPM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isCi = $this->isCiEnvironment();

        $cwd = getcwd();
        $envPath = $cwd . '/.env';
        $composerJsonPath = $cwd . '/composer.json';
        $packageJsonPath = $cwd . '/package.json';
        $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return Command::FAILURE;
        }

        // Verifico se il file .env ha la APP_KEY, se non ne ha una la creo
        $ENV_FILE = Dotenv::createImmutable($cwd);
        $ENV_FILE->safeLoad();

        if (!$isCi) {
            if (!$this->ensureCommandInstalled('node', ['node', '-v'], $output)) {
                return Command::FAILURE;
            }

            if (!$this->ensureCommandInstalled('npm', ['npm', '-v'], $output)) {
                return Command::FAILURE;
            }
        }

        if (!isset($_ENV['APP_KEY'])) {
            
            $appKeyAdded = false;
            $appKey = bin2hex(random_bytes(32));

            foreach ($lines as $i => $line) {
                if (preg_match("/^APP_URL=/", $line)) {

                    array_splice($lines, $i + 1, 0, "APP_KEY=$appKey");
                    $appKeyAdded = true;

                    break;

                }
            }

            if (!$appKeyAdded) {
                $lines[] = "APP_KEY=$appKey";
            }
            
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

            $output->writeln('<info>✅ Aggiungo un APP_KEY al file .env</info>');

        }

        $keyToIndex = $this->envKeyToIndex($lines);
        $updatedKeys = [];

        $appDomain = $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));
        if ($appDomain === '') {
            $appDomain = $this->defaultAppDomain($cwd);

            if ($appDomain !== '') {
                $output->writeln('<info>✅ APP_DOMAIN rilevato dalla cartella progetto: '.$appDomain.'</info>');
            } else {
                $appDomain = $this->askRequiredDomain($input, $output);
            }

            if ($appDomain === '') {
                return Command::FAILURE;
            }
        }

        if ($this->envValue($lines, $keyToIndex, 'APP_DOMAIN') !== $appDomain) {
            $this->setEnvValue($lines, $keyToIndex, 'APP_DOMAIN', $appDomain);
            $updatedKeys[] = 'APP_DOMAIN';
        }

        $appUrl = $this->buildAppUrl($appDomain);
        if ($this->envValue($lines, $keyToIndex, 'APP_URL') !== $appUrl) {
            $this->setEnvValue($lines, $keyToIndex, 'APP_URL', $appUrl);
            $updatedKeys[] = 'APP_URL';
        }

        if (count($updatedKeys) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: '.implode(', ', $updatedKeys).'</info>');
        }

        file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

        if (!$this->updateComposerName($composerJsonPath, $appDomain, $output)) {
            return Command::FAILURE;
        }

        $assetsVersion = $this->envValue($lines, $keyToIndex, 'ASSETS_VERSION');
        if ($assetsVersion === '') {
            $assetsVersion = '0.0';
            $this->setEnvValue($lines, $keyToIndex, 'ASSETS_VERSION', $assetsVersion);
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: ASSETS_VERSION</info>');
        }

        // Crea package.json se non esiste
        if (!file_exists($packageJsonPath)) {
            $package = [
                'private' => true,
                'dependencies' => new \stdClass()
            ];

            file_put_contents($packageJsonPath, json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $output->writeln('<info>✅ package.json creato.</info>');
        } else {
            $output->writeln('<comment>⚠️ package.json già presente.</comment>');
        }

        if (!$isCi) {
            // In locale materializza node_modules/wonder-image.
            if (!$this->runPassthruCommand('npm install wonder-image', $output, 'Impossibile installare wonder-image lato NPM.')) {
                return Command::FAILURE;
            }
        } else {
            $output->writeln('<comment>ℹ️ Ambiente CI rilevato: salto npm install wonder-image.</comment>');
        }

        if (!$isCi) {
            $output->writeln('<comment>ℹ️ Esegui `php forge provision` per GitHub e Bitwarden.</comment>');
            $output->writeln('<comment>ℹ️ Esegui `php forge update --local` per generare handler e file locali.</comment>');
        } else {
            $output->writeln('<comment>ℹ️ Esegui `php forge update` per i task applicativi.</comment>');
        }

        $output->writeln("<info>✅ Configurazione completata.</info>");

        return Command::SUCCESS;

    }

    protected function isCiEnvironment(): bool
    {
        foreach (['CI', 'GITHUB_ACTIONS'] as $key) {
            $value = $_ENV[$key] ?? getenv($key);

            if (!is_string($value)) {
                continue;
            }

            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function ensureBitwardenProjectId(string $appDomain, string $accessToken, OutputInterface $output): string
    {
        if (!$this->commandExists('bws')) {
            $output->writeln('<error>❌ bws non installato. Installa la CLI ufficiale Bitwarden Secrets Manager per creare BWS_PROJECT_ID automaticamente.</error>');
            return '';
        }

        $projects = $this->runCommand(
            ['bws', 'project', 'list', '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $accessToken]
        );

        if ($projects['exitCode'] !== 0) {
            $error = $projects['stderr'] !== '' ? $projects['stderr'] : 'Impossibile leggere i progetti Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return '';
        }

        $projectsJson = json_decode($projects['stdout'], true);

        if (!is_array($projectsJson)) {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la lettura dei project.</error>');
            return '';
        }

        foreach ($projectsJson as $project) {
            $name = strtolower(trim((string) ($project['name'] ?? '')));

            if ($name !== strtolower($appDomain)) {
                continue;
            }

            $projectId = trim((string) ($project['id'] ?? ''));

            if ($projectId !== '') {
                $output->writeln('<info>✅ Project Bitwarden già presente: '.$appDomain.'</info>');
                return $projectId;
            }
        }

        $output->writeln('<info>📦 Creo il project Bitwarden: '.$appDomain.'</info>');

        $project = $this->runCommand(
            ['bws', 'project', 'create', $appDomain, '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $accessToken]
        );

        if ($project['exitCode'] !== 0) {
            $error = $project['stderr'] !== '' ? $project['stderr'] : 'Impossibile creare il project Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return '';
        }

        $projectJson = json_decode($project['stdout'], true);
        $projectId = trim((string) ($projectJson['id'] ?? ''));

        if ($projectId === '') {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la creazione del project.</error>');
            return '';
        }

        $output->writeln('<info>✅ Project Bitwarden creato: '.$appDomain.'</info>');

        return $projectId;
    }

    protected function ensureGithubRepository(string $appDomain, OutputInterface $output): bool
    {
        if (!$this->commandExists('gh')) {
            $output->writeln('<error>❌ gh non installato. Impossibile creare la repository GitHub automaticamente.</error>');
            return false;
        }

        $repo = $this->runCommand(['gh', 'repo', 'view', $appDomain, '--json', 'id']);
        if ($repo['exitCode'] === 0) {
            $output->writeln('<info>✅ Repository GitHub già presente: '.$appDomain.'</info>');
            return true;
        }

        $auth = $this->runCommand(['gh', 'auth', 'status']);
        if ($auth['exitCode'] !== 0) {
            $output->writeln('<error>❌ Autenticazione GitHub non valida. Esegui gh auth login prima di creare la repository.</error>');
            return false;
        }

        $output->writeln('<info>📦 Creo la repository GitHub: '.$appDomain.'</info>');

        $create = $this->runCommand(['gh', 'repo', 'create', $appDomain, '--private']);

        if ($create['exitCode'] !== 0) {
            $error = $create['stderr'] !== '' ? $create['stderr'] : 'Impossibile creare la repository GitHub.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return false;
        }

        $output->writeln('<info>✅ Repository GitHub creata: '.$appDomain.'</info>');

        return true;
    }

    protected function syncGithubRepositorySecrets(string $appDomain, array $secrets, OutputInterface $output): bool
    {
        $repoName = $this->resolveGithubRepositoryName($appDomain);

        if ($repoName === '') {
            $output->writeln('<error>❌ Impossibile determinare il nome completo della repository GitHub.</error>');
            return false;
        }

        foreach ($secrets as $key => $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $result = $this->runCommand([
                'gh', 'secret', 'set', $key,
                '--repo', $repoName,
                '--body', $value,
            ]);

            if ($result['exitCode'] !== 0) {
                $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare i repository secrets GitHub.';
                $output->writeln('<error>❌ '.$error.'</error>');
                return false;
            }
        }

        $output->writeln('<info>✅ Repository secrets GitHub aggiornati: BWS_ACCESS_TOKEN, BWS_PROJECT_ID</info>');

        return true;
    }

    protected function syncGithubRepositoryVariables(string $appDomain, array $variables, OutputInterface $output): bool
    {
        $repoName = $this->resolveGithubRepositoryName($appDomain);

        if ($repoName === '') {
            $output->writeln('<error>❌ Impossibile determinare il nome completo della repository GitHub.</error>');
            return false;
        }

        foreach ($variables as $key => $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $result = $this->runCommand([
                'gh', 'variable', 'set', $key,
                '--repo', $repoName,
                '--body', $value,
            ]);

            if ($result['exitCode'] !== 0) {
                $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare le repository variables GitHub.';
                $output->writeln('<error>❌ '.$error.'</error>');
                return false;
            }
        }

        $output->writeln('<info>✅ Repository variables GitHub aggiornate: APP_DOMAIN, ASSETS_VERSION</info>');

        return true;
    }

    protected function syncBitwardenProjectSecrets(string $bwProjectId, string $bwAccessToken, array $lines, array $keyToIndex, OutputInterface $output): bool
    {
        $map = $this->bitwardenProjectSecretMap();

        $existingSecrets = $this->bitwardenProjectSecrets($bwProjectId, $bwAccessToken, $output);

        if ($existingSecrets === null) {
            return false;
        }

        $updated = [];

        foreach ($map as $envKey => $secretKeys) {
            $value = $this->envValue($lines, $keyToIndex, $envKey);

            if ($value === '') {
                continue;
            }

            foreach ($secretKeys as $secretKey) {
                $secretId = $existingSecrets[$secretKey] ?? '';

                if ($secretId !== '') {
                    $result = $this->runCommand(
                        ['bws', 'secret', 'edit', '--value', $value, $secretId, '--output', 'json'],
                        ['BWS_ACCESS_TOKEN' => $bwAccessToken]
                    );
                } else {
                    $result = $this->runCommand(
                        ['bws', 'secret', 'create', $secretKey, $value, $bwProjectId, '--output', 'json'],
                        ['BWS_ACCESS_TOKEN' => $bwAccessToken]
                    );
                }

                if ($result['exitCode'] !== 0) {
                    $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile aggiornare i secrets Bitwarden.';
                    $output->writeln('<error>❌ '.$error.'</error>');
                    return false;
                }

                $updated[] = $secretKey;
            }
        }

        if (count($updated) > 0) {
            $output->writeln('<info>✅ Secrets Bitwarden aggiornati: '.implode(', ', $updated).'</info>');
        }

        return true;
    }

    protected function ensureBitwardenProjectEnvValues(InputInterface $input, OutputInterface $output, string $envPath, array &$lines, array &$keyToIndex): bool
    {
        $updated = [];

        foreach (array_keys($this->bitwardenProjectSecretMap()) as $envKey) {
            if ($this->envValue($lines, $keyToIndex, $envKey) !== '') {
                continue;
            }

            $value = $this->askRequiredValue(
                $input,
                $output,
                $envKey,
                'Inserisci '.$envKey.':',
                in_array($envKey, ['DB_PASSWORD', 'FTP_PASSWORD'], true)
            );

            if ($value === '') {
                return false;
            }

            $this->setEnvValue($lines, $keyToIndex, $envKey, $value);
            $updated[] = $envKey;
        }

        if (count($updated) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
            $output->writeln('<info>✅ Configurazione aggiornata nel file .env: '.implode(', ', $updated).'</info>');
        }

        return true;
    }

    protected function bitwardenProjectSecretMap(): array
    {
        return [
            'APP_KEY' => ['APP_KEY'],
            'DB_HOSTNAME' => ['DB_HOSTNAME', 'DB_HOST'],
            'DB_USERNAME' => ['DB_USERNAME', 'DB_USER'],
            'DB_PASSWORD' => ['DB_PASSWORD'],
            'DB_DATABASE' => ['DB_DATABASE', 'DB_NAME'],
            'FTP_HOST' => ['FTP_HOST'],
            'FTP_PASSWORD' => ['FTP_PASSWORD'],
            'FTP_USER' => ['FTP_USER'],
            'FTP_PORT' => ['FTP_PORT'],
            'FTP_REMOTE_PATH' => ['FTP_REMOTE_PATH'],
        ];
    }

    protected function bitwardenProjectSecrets(string $bwProjectId, string $bwAccessToken, OutputInterface $output): ?array
    {
        $result = $this->runCommand(
            ['bws', 'secret', 'list', $bwProjectId, '--output', 'json'],
            ['BWS_ACCESS_TOKEN' => $bwAccessToken]
        );

        if ($result['exitCode'] !== 0) {
            $error = $result['stderr'] !== '' ? $result['stderr'] : 'Impossibile leggere i secrets Bitwarden.';
            $output->writeln('<error>❌ '.$error.'</error>');
            return null;
        }

        $payload = json_decode($result['stdout'], true);

        if (!is_array($payload)) {
            $output->writeln('<error>❌ Risposta Bitwarden non valida durante la lettura dei secrets.</error>');
            return null;
        }

        $secrets = [];

        foreach ($payload as $secret) {
            $key = trim((string) ($secret['key'] ?? ''));
            $id = trim((string) ($secret['id'] ?? ''));

            if ($key === '' || $id === '') {
                continue;
            }

            $secrets[$key] = $id;
        }

        return $secrets;
    }

    protected function resolveGithubRepositoryName(string $appDomain): string
    {
        $repo = $this->runCommand(['gh', 'repo', 'view', $appDomain, '--json', 'nameWithOwner', '--jq', '.nameWithOwner']);

        if ($repo['exitCode'] !== 0) {
            return '';
        }

        return trim($repo['stdout']);
    }

    protected function updateComposerName(string $composerJsonPath, string $appDomain, OutputInterface $output): bool
    {
        if (!file_exists($composerJsonPath)) {
            $output->writeln('<error>❌ composer.json non trovato.</error>');
            return false;
        }

        $composerJson = file_get_contents($composerJsonPath);

        if ($composerJson === false) {
            $output->writeln('<error>❌ Impossibile leggere composer.json</error>');
            return false;
        }

        $name = 'wonder-image/'.$this->composerProjectName($appDomain);

        if (preg_match('/"name"\s*:\s*"([^"]+)"/', $composerJson, $matches) !== 1) {
            $output->writeln('<error>❌ Chiave name non trovata in composer.json</error>');
            return false;
        }

        if ($matches[1] === $name) {
            return true;
        }

        $updatedComposerJson = preg_replace('/"name"\s*:\s*"([^"]+)"/', '"name": "'.$name.'"', $composerJson, 1);

        if ($updatedComposerJson === null) {
            $output->writeln('<error>❌ Impossibile aggiornare il name in composer.json</error>');
            return false;
        }

        file_put_contents($composerJsonPath, $updatedComposerJson);

        $output->writeln('<info>✅ Aggiorno composer.json: '.$name.'</info>');

        return true;
    }

    protected function envKeyToIndex(array $lines): array
    {
        $keyToIndex = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches) === 1) {
                $keyToIndex[$matches[1]] = $i;
            }
        }

        return $keyToIndex;
    }

    protected function envValue(array $lines, array $keyToIndex, string $key): string
    {
        if (!isset($keyToIndex[$key])) {
            return '';
        }

        $line = $lines[$keyToIndex[$key]];
        $parts = explode('=', $line, 2);
        $raw = $parts[1] ?? '';

        return trim(trim($raw), "\"'");
    }

    protected function setEnvValue(array &$lines, array &$keyToIndex, string $key, string $value): void
    {
        $row = $key.'='.$value;

        if (isset($keyToIndex[$key])) {
            $lines[$keyToIndex[$key]] = $row;
            return;
        }

        $lines[] = $row;
        $keyToIndex[$key] = count($lines) - 1;
    }

    protected function removeEnvValue(array &$lines, array &$keyToIndex, string $key): void
    {
        if (!isset($keyToIndex[$key])) {
            return;
        }

        unset($lines[$keyToIndex[$key]]);
        $lines = array_values($lines);
        $keyToIndex = $this->envKeyToIndex($lines);
    }

    protected function askRequiredDomain(InputInterface $input, OutputInterface $output): string
    {
        while (true) {
            $domain = $this->askRequiredValue($input, $output, 'APP_DOMAIN', 'Inserisci APP_DOMAIN:');

            if ($domain === '') {
                return '';
            }

            $domain = $this->normalizeDomain($domain);

            if ($domain !== '') {
                return $domain;
            }

            $output->writeln('<error>❌ APP_DOMAIN non valido.</error>');
        }
    }

    protected function askRequiredValue(InputInterface $input, OutputInterface $output, string $key, string $question, bool $hidden = false): string
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>❌ '.$key.' mancante. Esegui il comando in modalità interattiva.</error>');
            return '';
        }

        $helper = $this->getHelper('question');

        while (true) {
            try {
                $prompt = new Question($question.' ');

                if ($hidden && method_exists($prompt, 'setHidden')) {
                    $prompt->setHidden(true);

                    if (method_exists($prompt, 'setHiddenFallback')) {
                        $prompt->setHiddenFallback(false);
                    }
                } elseif ($hidden) {
                    $output->writeln('<comment>⚠️ Input nascosto non disponibile, continuo con input visibile.</comment>');
                }

                $value = trim((string) $helper->ask($input, $output, $prompt));
            } catch (\RuntimeException $e) {
                if (!$hidden) {
                    throw $e;
                }

                $output->writeln('<comment>⚠️ Input nascosto non disponibile, continuo con input visibile.</comment>');
                $hidden = false;
                continue;
            }

            if ($value !== '') {
                return $value;
            }

            $output->writeln('<error>❌ '.$key.' obbligatorio.</error>');
        }
    }

    protected function normalizeDomain(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https?://#i', '', $value);
        $value = trim((string) $value, "/ \t\n\r\0\x0B");
        $host = parse_url('https://'.$value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $value = $host;
        }

        return strtolower(trim($value));
    }

    protected function defaultAppDomain(string $cwd): string
    {
        $folder = trim(basename($cwd));

        if ($folder === '' || $folder === '.' || $folder === DIRECTORY_SEPARATOR) {
            return '';
        }

        return $this->normalizeDomain($folder);
    }

    protected function buildAppUrl(string $appDomain): string
    {
        return 'https://'.$appDomain;
    }

    protected function composerProjectName(string $appDomain): string
    {
        $name = preg_replace('/[^a-z0-9._-]+/', '-', strtolower($appDomain));
        $name = trim((string) $name, '-._');

        return $name !== '' ? $name : 'app';
    }

    protected function commandExists(string $command): bool
    {
        $check = $this->runCommand(['sh', '-lc', 'command -v '.escapeshellarg($command).' >/dev/null 2>&1']);

        return $check['exitCode'] === 0;
    }

    protected function ensureCommandInstalled(string $command, array $versionCommand, OutputInterface $output, bool $autoInstall = true): bool
    {
        if (!$this->commandExists($command)) {
            if (!$autoInstall) {
                $output->writeln('<error>❌ '.$command.' non installato.</error>');
                return false;
            }

            $output->writeln('<comment>⚠️ '.$command.' non installato. Provo a installarlo.</comment>');

            if (!$this->installCommand($command, $output)) {
                return false;
            }
        }

        $version = $this->runCommand($versionCommand);

        if ($version['exitCode'] !== 0) {
            $output->writeln('<comment>⚠️ '.$command.' installato ma non disponibile correttamente. Provo a reinstallarlo.</comment>');

            if (!$this->installCommand($command, $output)) {
                return false;
            }

            $version = $this->runCommand($versionCommand);

            if ($version['exitCode'] !== 0) {
                $output->writeln('<error>❌ '.$command.' installato ma non disponibile correttamente.</error>');
                return false;
            }
        }

        $message = $version['stdout'] !== '' ? $version['stdout'] : $version['stderr'];

        if ($message !== '') {
            $message = strtok($message, PHP_EOL);
            $output->writeln('<info>✅ '.$command.' installato: '.$message.'</info>');
            return true;
        }

        $output->writeln('<info>✅ '.$command.' installato.</info>');

        return true;
    }

    protected function installCommand(string $command, OutputInterface $output): bool
    {
        return match ($command) {
            'node', 'npm' => $this->installWithBrew('node', $output),
            'gh' => $this->installWithBrew('gh', $output),
            'bws' => $this->installBws($output),
            default => false,
        };
    }

    protected function installWithBrew(string $formula, OutputInterface $output): bool
    {
        if (!$this->commandExists('brew')) {
            $output->writeln('<error>❌ brew non installato. Impossibile installare '.$formula.' automaticamente.</error>');
            return false;
        }

        $output->writeln('<info>📦 Eseguo: brew install '.$formula.'</info>');
        passthru('brew install '.escapeshellarg($formula), $exitCode);

        return $exitCode === 0;
    }

    protected function installBws(OutputInterface $output): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $output->writeln('<error>❌ Installazione automatica di bws supportata solo su macOS.</error>');
            return false;
        }

        if (!$this->commandExists('brew')) {
            $output->writeln('<error>❌ brew non installato. Impossibile installare bws automaticamente.</error>');
            return false;
        }

        $target = $this->bitwardenMacTarget();

        if ($target === '') {
            $output->writeln('<error>❌ Architettura macOS non supportata per installare bws automaticamente.</error>');
            return false;
        }

        $brewPrefix = trim((string) $this->runCommand(['brew', '--prefix'])['stdout']);

        if ($brewPrefix === '') {
            $output->writeln('<error>❌ Impossibile determinare il percorso di brew.</error>');
            return false;
        }

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wonder-bws-'.getmypid();
        $assetUrl = 'https://github.com/bitwarden/sdk-sm/releases/latest/download/'.$target;
        $installPath = $brewPrefix.'/bin/bws';

        $command = 'rm -rf '.escapeshellarg($tempDir)
            .' && mkdir -p '.escapeshellarg($tempDir)
            .' && curl -fsSL '.escapeshellarg($assetUrl).' -o '.escapeshellarg($tempDir.'/bws.zip')
            .' && unzip -oq '.escapeshellarg($tempDir.'/bws.zip').' -d '.escapeshellarg($tempDir)
            .' && chmod +x '.escapeshellarg($tempDir.'/bws')
            .' && mv '.escapeshellarg($tempDir.'/bws').' '.escapeshellarg($installPath)
            .' && rm -rf '.escapeshellarg($tempDir);

        $output->writeln('<info>📦 Eseguo installazione bws da release ufficiale Bitwarden</info>');
        passthru($command, $exitCode);

        return $exitCode === 0;
    }

    protected function bitwardenMacTarget(): string
    {
        $machine = strtolower(php_uname('m'));

        return match ($machine) {
            'arm64', 'aarch64' => 'bws-aarch64-apple-darwin.zip',
            'x86_64' => 'bws-x86_64-apple-darwin.zip',
            default => '',
        };
    }

    protected function runCommand(array|string $command, array $env = []): array
    {
        $processEnv = getenv();

        if (!is_array($processEnv)) {
            $processEnv = [];
        }

        if (is_array($command)) {
            $command = $this->buildShellCommand($command);
        }

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            array_merge($processEnv, $env)
        );

        if (!is_resource($process)) {
            return [
                'stdout' => '',
                'stderr' => '',
                'exitCode' => 1,
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
            'exitCode' => proc_close($process),
        ];
    }

    protected function buildShellCommand(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }

    protected function runPassthruCommand(string $command, OutputInterface $output, string $errorMessage): bool
    {
        $output->writeln('<info>📦 Eseguo: '.$command.'</info>');
        passthru($command, $exitCode);

        if ($exitCode === 0) {
            return true;
        }

        $output->writeln('<error>❌ '.$errorMessage.'</error>');

        return false;
    }
}
