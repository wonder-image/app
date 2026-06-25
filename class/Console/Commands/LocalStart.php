<?php

namespace Wonder\Console\Commands;

use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LocalStart extends LocalEnvironmentCommand
{
    public $name = 'start';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setAliases(['local:start'])
            ->setDescription('Avvia il progetto in locale con routing compatibile (/, /backend/, /api/).')
            ->addOption('driver', null, InputOption::VALUE_REQUIRED, 'Driver locale: auto, herd, php', 'auto')
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'Versione PHP da isolare con Herd (es. 8.4)')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host server locale', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta server locale', '8088')
            ->addOption('docroot', null, InputOption::VALUE_REQUIRED, 'Document root del progetto', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd() ?: '.';
        $driver = strtolower(trim((string) $input->getOption('driver')));
        $host = trim((string) $input->getOption('host'));
        $port = (int) $input->getOption('port');
        $docroot = $this->resolveDocroot($cwd, (string) $input->getOption('docroot'));
        $runtimeDriver = $this->resolveLocalRuntimeDriver($driver);
        $phpVersion = trim((string) $input->getOption('php-version'));

        if ($runtimeDriver === 'invalid') {
            $output->writeln('<error>❌ Driver non valido. Usa auto, herd oppure php.</error>');
            return Command::FAILURE;
        }

        if ($runtimeDriver === 'missing-herd') {
            $output->writeln('<error>❌ Laravel Herd non installato o non disponibile nel PATH.</error>');
            $output->writeln('<comment>ℹ️ Installa/configura Herd oppure usa `php forge start --driver=php`.</comment>');
            return Command::FAILURE;
        }

        if ($host === '') {
            $output->writeln('<error>❌ Host non valido.</error>');
            return Command::FAILURE;
        }

        if (!$this->validatePort($port)) {
            $output->writeln('<error>❌ Porta non valida. Usa un valore tra 1 e 65535.</error>');
            return Command::FAILURE;
        }

        if ($docroot === null || !is_dir($docroot)) {
            $output->writeln('<error>❌ Docroot non valida.</error>');
            return Command::FAILURE;
        }

        $hasIndex = file_exists($docroot.'/index.php');
        $hasHandler = file_exists($docroot.'/handler/index.php');

        if (!$hasIndex && !$hasHandler) {
            $output->writeln('<error>❌ Nessun front controller trovato nella docroot: manca sia index.php sia handler/index.php.</error>');
            return Command::FAILURE;
        }

        if (!$this->ensureEnv($cwd, $host, $port, $runtimeDriver, $output)) {
            return Command::FAILURE;
        }

        $this->loadEnv($cwd);
        // Strippa la TLD per evitare di costruire URL locali tipo
        // `fatimagabrielewedding-com.test` da APP_DOMAIN=`fatimagabrielewedding.com`.
        $appDomain = $this->defaultProjectLabel((string) ($_ENV['APP_DOMAIN'] ?? ''));
        $url = $this->resolveLocalAppUrl($appDomain !== '' ? $appDomain : $this->defaultAppDomain($cwd), $host, $port, $runtimeDriver);

        $this->printEnvHints($output, $url);
        $this->checkDatabase($output);

        if ($runtimeDriver === 'herd') {
            if ($appDomain === '') {
                $output->writeln('<error>❌ APP_DOMAIN non valido per Herd.</error>');
                return Command::FAILURE;
            }

            if (!$this->ensureHerdDriver($output)) {
                return Command::FAILURE;
            }

            if (!$this->ensureHerdSite($cwd, $appDomain, $phpVersion, $output)) {
                return Command::FAILURE;
            }

            $output->writeln('');
            $output->writeln('<info>✅ Sito locale pronto con Herd</info>');
            $output->writeln("  Home: {$url}/");
            $output->writeln("  Backend: {$url}/backend/");
            $output->writeln("  Login backend: {$url}/backend/account/login/");
            $output->writeln("  API update (POST): {$url}/api/app/update/");
            $output->writeln('  Driver: herd');
            $output->writeln('  PHP isolato: '.($phpVersion !== '' ? $phpVersion : $this->herdPhpVersion()));
            $output->writeln('');

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>✅ Server locale pronto</info>');
        $output->writeln("  Home: {$url}/");
        $output->writeln("  Backend: {$url}/backend/");
        $output->writeln("  Login backend: {$url}/backend/account/login/");
        $output->writeln("  API update (POST): {$url}/api/app/update/");
        $output->writeln('  Stop: CTRL+C');
        $output->writeln('');

        $routerPath = $this->createRouter($docroot);
        if ($routerPath === null) {
            $output->writeln('<error>❌ Impossibile creare il router locale temporaneo.</error>');
            return Command::FAILURE;
        }

        $command = sprintf(
            'php -S %s -t %s %s',
            escapeshellarg("{$host}:{$port}"),
            escapeshellarg($docroot),
            escapeshellarg($routerPath)
        );

        passthru($command, $exitCode);
        @unlink($routerPath);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveDocroot(string $cwd, string $docroot): ?string
    {
        $candidate = $docroot === '.'
            ? $cwd
            : (str_starts_with($docroot, DIRECTORY_SEPARATOR) ? $docroot : $cwd.DIRECTORY_SEPARATOR.$docroot);

        $resolved = realpath($candidate);

        return $resolved !== false ? rtrim($resolved, DIRECTORY_SEPARATOR) : null;
    }

    private function ensureEnv(string $cwd, string $host, int $port, string $driver, OutputInterface $output): bool
    {
        $lines = $this->readEnvLines($cwd, $output);

        if ($lines === null) {
            return false;
        }

        $keyToIndex = $this->envKeyToIndex($lines);
        // APP_DOMAIN è il dominio COMPLETO con estensione (`wonderimage.it`).
        // Confronto like-for-like (dominio vs dominio): un valore legacy
        // strippato/dash (`wonderimage` o `wonderimage-com`) differisce dal
        // nuovo default `wonderimage.it`, quindi il messaggio di sync segnala
        // solo le migrazioni reali e non scatta a ogni `forge start`.
        $existingAppDomain = $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));
        $appDomain = $this->defaultAppDomain($cwd);

        if ($appDomain !== '' && $appDomain !== $existingAppDomain) {
            $output->writeln('<info>✅ APP_DOMAIN sincronizzato dalla cartella progetto: '.$appDomain.'</info>');
        }

        $updatedKeys = $this->completeEnvValues($lines, $keyToIndex, [
            'APP_DOMAIN' => $appDomain !== '' ? $appDomain : null,
            'APP_URL' => $appDomain !== '' ? $this->resolveLocalAppUrl($appDomain, $host, $port, $driver) : null,
        ], true);

        $updatedKeys = array_merge($updatedKeys, $this->completeEnvValues($lines, $keyToIndex, [
            'APP_KEY' => bin2hex(random_bytes(32)),
            'USER_PASSWORD' => \Wonder\App\SeedDefaults::adminPassword(),
        ]));

        if (count($updatedKeys) === 0) {
            return true;
        }

        return $this->writeEnvLines($cwd, $lines, $output, $updatedKeys);
    }

    private function printEnvHints(OutputInterface $output, string $expectedAppUrl): void
    {
        $appUrl = trim((string) ($_ENV['APP_URL'] ?? ''));

        if ($appUrl === '') {
            $output->writeln("<comment>⚠️ APP_URL non impostata. Consigliato: {$expectedAppUrl}</comment>");
            return;
        }

        if ($appUrl !== $expectedAppUrl) {
            $output->writeln("<comment>⚠️ APP_URL={$appUrl}. Se vedi asset rotti usa {$expectedAppUrl}</comment>");
        }
    }

    private function ensureHerdDriver(OutputInterface $output): bool
    {
        $stubPath = dirname(__DIR__, 3).'/app/build/stubs/WonderValetDriver.php';
        $driversPath = $this->herdDriversPath();
        $targetPath = $driversPath.DIRECTORY_SEPARATOR.'WonderValetDriver.php';

        if (!file_exists($stubPath)) {
            $output->writeln('<error>❌ Stub WonderValetDriver.php non trovato nel package.</error>');
            return false;
        }

        $stub = file_get_contents($stubPath);

        if (!is_string($stub) || trim($stub) === '') {
            $output->writeln('<error>❌ Stub WonderValetDriver.php non valido.</error>');
            return false;
        }

        if (!is_dir($driversPath) && !mkdir($driversPath, 0777, true) && !is_dir($driversPath)) {
            $output->writeln('<error>❌ Impossibile creare la directory Drivers di Herd.</error>');
            return false;
        }

        $current = file_exists($targetPath) ? file_get_contents($targetPath) : false;

        if ($current === $stub) {
            return true;
        }

        if (file_put_contents($targetPath, $stub) === false) {
            $output->writeln('<error>❌ Impossibile creare o aggiornare WonderValetDriver.php nella configurazione di Herd.</error>');
            return false;
        }

        $output->writeln('<info>✅ WonderValetDriver.php sincronizzato nella configurazione di Herd.</info>');

        return true;
    }

    private function herdDriversPath(): string
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');

        return rtrim($home, DIRECTORY_SEPARATOR).'/Library/Application Support/Herd/config/valet/Drivers';
    }

    private function ensureHerdSite(string $cwd, string $appDomain, string $phpVersion, OutputInterface $output): bool
    {
        $host = $this->buildHerdHost($appDomain);

        $link = $this->runCommand(['herd', 'link', $appDomain], [], $cwd);
        if (!$this->herdCommandSucceeded($link, ['already linked', 'already exists', 'linked'])) {
            $message = $link['stderr'] !== '' ? $link['stderr'] : $link['stdout'];
            $output->writeln('<error>❌ Impossibile collegare il progetto a Herd'.($message !== '' ? ': '.$message : '.').'</error>');
            return false;
        }

        $secure = $this->runCommand(['herd', 'secure', $appDomain], [], $cwd);
        if (!$this->herdCommandSucceeded($secure, ['already secured', 'secured', 'tls', 'certificate'])) {
            $message = $secure['stderr'] !== '' ? $secure['stderr'] : $secure['stdout'];
            $output->writeln('<error>❌ Impossibile abilitare HTTPS su Herd'.($message !== '' ? ': '.$message : '.').'</error>');
            return false;
        }

        $resolvedPhpVersion = $phpVersion !== '' ? $phpVersion : $this->herdPhpVersion();
        $isolate = $this->runCommand(['herd', 'isolate', $resolvedPhpVersion, '--site='.$appDomain], [], $cwd);
        if (!$this->herdCommandSucceeded($isolate, ['isolated', 'already using', 'php version'])) {
            $message = $isolate['stderr'] !== '' ? $isolate['stderr'] : $isolate['stdout'];
            $output->writeln('<error>❌ Impossibile isolare la versione PHP su Herd'.($message !== '' ? ': '.$message : '.').'</error>');
            return false;
        }

        $output->writeln('<info>✅ Herd collegato su '.$host.'</info>');

        return true;
    }

    private function herdCommandSucceeded(array $result, array $allowedHints = []): bool
    {
        if (($result['exitCode'] ?? 1) === 0) {
            return true;
        }

        $message = strtolower(trim(((string) ($result['stdout'] ?? '')).' '.((string) ($result['stderr'] ?? ''))));

        foreach ($allowedHints as $hint) {
            if ($hint !== '' && str_contains($message, strtolower($hint))) {
                return true;
            }
        }

        return false;
    }

    private function checkDatabase(OutputInterface $output): void
    {
        $hostname = trim((string) ($_ENV['DB_HOSTNAME'] ?? ''));
        $username = (string) ($_ENV['DB_USERNAME'] ?? '');
        $password = (string) ($_ENV['DB_PASSWORD'] ?? '');
        $databaseRaw = (string) ($_ENV['DB_DATABASE'] ?? '');
        $database = $this->parseMainDatabase($databaseRaw);

        if ($hostname === '' || $username === '' || $database === '') {
            $output->writeln('<comment>⚠️ Configurazione DB incompleta nel file .env.</comment>');
            $output->writeln('<comment>ℹ️ Esegui `php forge db:init` per inizializzare database e utente locale.</comment>');
            return;
        }

        [$host, $port] = $this->parseHostPort($hostname);

        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new mysqli($host, $username, $password, $database, $port);

        if ($mysqli->connect_errno) {
            $output->writeln('<comment>⚠️ DB non raggiungibile: '.$mysqli->connect_error.'</comment>');
            $output->writeln('<comment>ℹ️ Se il database locale non è stato ancora creato esegui `php forge db:init`.</comment>');
            return;
        }

        $output->writeln('<info>✅ Connessione DB OK ('.$host.($port > 0 ? ':'.$port : '').')</info>');
        $mysqli->close();
    }

    private function createRouter(string $docroot): ?string
    {
        $routerPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wonder-local-router-'.getmypid().'.php';
        $docrootExport = var_export($docroot, true);

        $router = <<<PHP
<?php
\$docroot = {$docrootExport};
\$uri = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
\$legacyUri = preg_replace('#/index\\.php$#', '/', \$uri);

if (is_string(\$legacyUri) && \$legacyUri !== '' && \$legacyUri !== \$uri) {
    \$query = isset(\$_SERVER['QUERY_STRING']) && \$_SERVER['QUERY_STRING'] !== ''
        ? '?'.\$_SERVER['QUERY_STRING']
        : '';
    \$uri = \$legacyUri;
    \$_SERVER['REQUEST_URI'] = \$uri.\$query;
    \$_SERVER['PHP_SELF'] = \$uri;
}

\$requestPath = \$docroot.\$uri;

if (\$uri !== '/' && is_file(\$requestPath)) {
    return false;
}

if (\$uri !== '/' && is_dir(\$requestPath) && file_exists(\$requestPath.'/index.php')) {
    require \$requestPath.'/index.php';
    return true;
}

if (\$uri !== '/' && file_exists(\$requestPath.'.php')) {
    require \$requestPath.'.php';
    return true;
}

\$handlerIndex = \$docroot.'/handler/index.php';

if (file_exists(\$handlerIndex)) {
    require \$handlerIndex;
    return true;
}

require \$docroot.'/index.php';
return true;
PHP;

        return file_put_contents($routerPath, $router) !== false ? $routerPath : null;
    }
}
