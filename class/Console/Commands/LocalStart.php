<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LocalStart extends Command
{
    public $name = 'start';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setAliases(['local:start'])
            ->setDescription('Avvia il progetto in locale con routing compatibile (/, /backend/, /update/).')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host server locale', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta server locale', '8088')
            ->addOption('docroot', null, InputOption::VALUE_REQUIRED, 'Document root del progetto', '.')
            ->addOption('db-hostname', null, InputOption::VALUE_REQUIRED, 'Default DB_HOSTNAME se vuoto in .env', '127.0.0.1:3307')
            ->addOption('db-username', null, InputOption::VALUE_REQUIRED, 'Default DB_USERNAME se vuoto in .env', 'sf8_test_user')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Default DB_PASSWORD se vuoto in .env', 'sf8_test_password')
            ->addOption('db-database', null, InputOption::VALUE_REQUIRED, 'Default DB_DATABASE se vuoto in .env', 'main:myapp_sf8_test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd() ?: '.';
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $docroot = $this->resolveDocroot($cwd, (string) $input->getOption('docroot'));
        $dbDefaults = [
            'hostname' => trim((string) $input->getOption('db-hostname')),
            'username' => trim((string) $input->getOption('db-username')),
            'password' => (string) $input->getOption('db-password'),
            'database' => trim((string) $input->getOption('db-database')),
        ];

        if ($port < 1 || $port > 65535) {
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

        $this->ensureEnv($cwd, $host, $port, $dbDefaults, $output);
        $this->loadEnv($cwd);
        $this->printEnvHints($output, $host, $port);
        $this->checkDatabase($output);

        $routerPath = $this->createRouter($docroot);
        if ($routerPath === null) {
            $output->writeln('<error>❌ Impossibile creare il router locale temporaneo.</error>');
            return Command::FAILURE;
        }

        $url = "http://{$host}:{$port}";

        $output->writeln('');
        $output->writeln('<info>✅ Server locale pronto</info>');
        $output->writeln("  Home: {$url}/");
        $output->writeln("  Backend: {$url}/backend/");
        $output->writeln("  Login backend: {$url}/backend/account/login/");
        $output->writeln("  Update (safe): {$url}/update/");
        $output->writeln("  Esegui update: {$url}/update/run/");
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

    private function ensureEnv(string $cwd, string $host, int $port, array $dbDefaults, OutputInterface $output): void
    {
        $envPath = $cwd.'/.env';

        if (!file_exists($envPath)) {
            file_put_contents($envPath, $this->defaultEnvTemplate());
            $output->writeln('<info>✅ File .env creato automaticamente.</info>');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $output->writeln('<comment>⚠️ Impossibile leggere il file .env</comment>');
            return;
        }

        $keyToIndex = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $m) === 1) {
                $keyToIndex[$m[1]] = $i;
            }
        }

        $appUrl = "http://{$host}:{$port}";
        $dbPasswordDefault = trim($dbDefaults['password']) !== ''
            ? (string) $dbDefaults['password']
            : 'sf8_test_password';

        $updates = [
            'APP_URL' => $appUrl,
            'APP_KEY' => bin2hex(random_bytes(32)),
            'DB_HOSTNAME' => $dbDefaults['hostname'] !== '' ? $dbDefaults['hostname'] : '127.0.0.1:3307',
            'DB_USERNAME' => $dbDefaults['username'] !== '' ? $dbDefaults['username'] : 'sf8_test_user',
            'DB_PASSWORD' => $dbPasswordDefault,
            'DB_DATABASE' => $dbDefaults['database'] !== '' ? $dbDefaults['database'] : 'main:myapp_sf8_test',
            'USER_PASSWORD' => $this->randomAlphaNumeric(8),
        ];

        $updatedKeys = [];

        foreach ($updates as $key => $value) {
            $existing = $this->envValue($lines, $keyToIndex, $key);
            if (!$this->isMissingEnvValue($existing, $key)) {
                continue;
            }

            $row = $key.'='.$value;

            if (isset($keyToIndex[$key])) {
                $lines[$keyToIndex[$key]] = $row;
            } else {
                $lines[] = $row;
                $keyToIndex[$key] = count($lines) - 1;
            }

            $updatedKeys[] = $key;
        }

        if (count($updatedKeys) > 0) {
            file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);
            $safeKeys = array_map(fn($key) => in_array($key, ['DB_PASSWORD', 'USER_PASSWORD', 'APP_KEY'], true) ? "{$key}=***" : $key, $updatedKeys);
            $output->writeln('<info>✅ .env completato automaticamente: '.implode(', ', $safeKeys).'</info>');
        }
    }

    private function envValue(array $lines, array $keyToIndex, string $key): string
    {
        if (!isset($keyToIndex[$key])) {
            return '';
        }

        $line = $lines[$keyToIndex[$key]];
        $parts = explode('=', $line, 2);
        $raw = $parts[1] ?? '';

        return trim(trim($raw), "\"'");
    }

    private function isMissingEnvValue(string $value, string $key): bool
    {
        if ($value === '') {
            return true;
        }

        $normalized = strtolower(trim($value));

        $commonPlaceholders = [
            '...',
            'todo',
            'changeme',
            'change-me',
            'replace-me',
            'replace_this',
            'null',
        ];

        if (in_array($normalized, $commonPlaceholders, true)) {
            return true;
        }

        $keySpecific = [
            'APP_KEY' => ['metti_una_chiave_random', 'una_chiave_random'],
            'DB_USERNAME' => ['tuo_db_user', 'db_user', 'username'],
            'DB_PASSWORD' => ['tua_db_pass', 'db_pass', 'password'],
            'DB_DATABASE' => ['db_name', 'my_database'],
            'USER_PASSWORD' => ['password', 'user_password'],
        ];

        if (isset($keySpecific[$key]) && in_array($normalized, $keySpecific[$key], true)) {
            return true;
        }

        return false;
    }

    private function randomAlphaNumeric(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($alphabet) - 1;
        $output = '';

        for ($i = 0; $i < $length; $i++) {
            $output .= $alphabet[random_int(0, $max)];
        }

        return $output;
    }

    private function defaultEnvTemplate(): string
    {
        return <<<ENV
# App Info
APP_DEBUG=true
APP_URL=
APP_KEY=

# Assets
ASSETS_VERSION=0.0

# Database
DB_HOSTNAME=
DB_USERNAME=
DB_PASSWORD=
DB_DATABASE=
DB_CONNECTION_LOG=false

# Backend default user
USER_NAME=Admin
USER_SURNAME=User
USER_EMAIL=admin@example.local
USER_USERNAME=admin
USER_PASSWORD=
ENV;
    }

    private function loadEnv(string $cwd): void
    {
        $envPath = $cwd.'/.env';

        if (!file_exists($envPath)) {
            return;
        }

        $ENV_FILE = Dotenv::createImmutable($cwd);
        $ENV_FILE->safeLoad();
    }

    private function printEnvHints(OutputInterface $output, string $host, int $port): void
    {
        $expectedAppUrl = "http://{$host}:{$port}";
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
            $output->writeln('<comment>⚠️ Credenziali DB incomplete nel file .env</comment>');
            return;
        }

        [$host, $port] = $this->parseHostPort($hostname);

        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new mysqli($host, $username, $password, $database, $port);

        if ($mysqli->connect_errno) {
            $output->writeln('<comment>⚠️ DB non raggiungibile: '.$mysqli->connect_error.'</comment>');
            return;
        }

        $output->writeln('<info>✅ Connessione DB OK ('.$host.($port > 0 ? ':'.$port : '').')</info>');
        $mysqli->close();
    }

    private function parseMainDatabase(string $database): string
    {
        $database = trim($database);
        if ($database === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $database));
        $first = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($first === '') {
                $first = $part;
            }

            if (str_contains($part, ':')) {
                [$key, $value] = array_map('trim', explode(':', $part, 2));
                if ($key === 'main') {
                    return $value;
                }
            }
        }

        return str_contains($first, ':')
            ? trim((string) explode(':', $first, 2)[1])
            : $first;
    }

    private function parseHostPort(string $hostname): array
    {
        if (preg_match('/^(.+):(\d+)$/', $hostname, $matches) === 1) {
            return [trim($matches[1]), (int) $matches[2]];
        }

        return [$hostname, 0];
    }

    private function createRouter(string $docroot): ?string
    {
        $routerPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wonder-local-router-'.getmypid().'.php';
        $docrootExport = var_export($docroot, true);

        $router = <<<PHP
<?php
\$docroot = {$docrootExport};
\$uri = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
\$requestPath = \$docroot.\$uri;

if (\$uri === '/update' || \$uri === '/update/') {
    \$runPath = '/update/run/';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Update</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:2rem;max-width:760px;margin:0 auto;line-height:1.5}code{background:#f3f3f3;padding:.2rem .4rem;border-radius:.25rem}.btn{display:inline-block;padding:.6rem .9rem;background:#111;color:#fff;text-decoration:none;border-radius:.35rem}</style></head><body><h1>Update protetto</h1><p>Questa pagina non esegue aggiornamenti automaticamente.</p><p>Per eseguire realmente lo script usa <code>/update/run/</code>.</p><p><a class="btn" href="'.\$runPath.'">Esegui update ora</a></p></body></html>';
    return true;
}

if (\$uri === '/update/run' || \$uri === '/update/run/') {
    \$_GET['updateApp'] = 'true';
    require \$docroot.'/index.php';
    return true;
}

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

require \$docroot.'/index.php';
return true;
PHP;

        return file_put_contents($routerPath, $router) !== false ? $routerPath : null;
    }
}
