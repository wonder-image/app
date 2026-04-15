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
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host server locale', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta server locale', '8088')
            ->addOption('docroot', null, InputOption::VALUE_REQUIRED, 'Document root del progetto', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd() ?: '.';
        $host = trim((string) $input->getOption('host'));
        $port = (int) $input->getOption('port');
        $docroot = $this->resolveDocroot($cwd, (string) $input->getOption('docroot'));

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

        if (!file_exists($docroot.'/index.php')) {
            $output->writeln('<error>❌ Nessun index.php trovato nella docroot.</error>');
            return Command::FAILURE;
        }

        if (!$this->ensureEnv($cwd, $host, $port, $output)) {
            return Command::FAILURE;
        }

        $this->loadEnv($cwd);
        $this->printEnvHints($output, $host, $port);
        $this->checkDatabase($output);

        $routerPath = $this->createRouter($docroot);
        if ($routerPath === null) {
            $output->writeln('<error>❌ Impossibile creare il router locale temporaneo.</error>');
            return Command::FAILURE;
        }

        $url = $this->buildLocalAppUrl($host, $port);

        $output->writeln('');
        $output->writeln('<info>✅ Server locale pronto</info>');
        $output->writeln("  Home: {$url}/");
        $output->writeln("  Backend: {$url}/backend/");
        $output->writeln("  Login backend: {$url}/backend/account/login/");
        $output->writeln("  API update (POST): {$url}/api/app/update/");
        $output->writeln('  Stop: CTRL+C');
        $output->writeln('');

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

    private function ensureEnv(string $cwd, string $host, int $port, OutputInterface $output): bool
    {
        $lines = $this->readEnvLines($cwd, $output);

        if ($lines === null) {
            return false;
        }

        $keyToIndex = $this->envKeyToIndex($lines);
        $existingAppDomain = $this->normalizeDomain($this->envValue($lines, $keyToIndex, 'APP_DOMAIN'));
        $appDomain = $this->defaultAppDomain($cwd);

        if ($appDomain !== '' && $appDomain !== $existingAppDomain) {
            $output->writeln('<info>✅ APP_DOMAIN sincronizzato dalla cartella progetto: '.$appDomain.'</info>');
        }

        $updatedKeys = $this->completeEnvValues($lines, $keyToIndex, [
            'APP_DOMAIN' => $appDomain !== '' ? $appDomain : null,
            'APP_URL' => $this->buildLocalAppUrl($host, $port),
        ], true);

        $updatedKeys = array_merge($updatedKeys, $this->completeEnvValues($lines, $keyToIndex, [
            'APP_KEY' => bin2hex(random_bytes(32)),
            'USER_PASSWORD' => $this->randomAlphaNumeric(8),
        ]));

        if (count($updatedKeys) === 0) {
            return true;
        }

        return $this->writeEnvLines($cwd, $lines, $output, $updatedKeys);
    }

    private function printEnvHints(OutputInterface $output, string $host, int $port): void
    {
        $expectedAppUrl = $this->buildLocalAppUrl($host, $port);
        $appUrl = trim((string) ($_ENV['APP_URL'] ?? ''));

        if ($appUrl === '') {
            $output->writeln("<comment>⚠️ APP_URL non impostata. Consigliato: {$expectedAppUrl}</comment>");
            return;
        }

        if ($appUrl !== $expectedAppUrl) {
            $output->writeln("<comment>⚠️ APP_URL={$appUrl}. Se vedi asset rotti usa {$expectedAppUrl}</comment>");
        }
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
