<?php

namespace Wonder\Console\Commands;

use Dotenv\Dotenv;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Wonder\App\RuntimeDefaults;

abstract class LocalEnvironmentCommand extends Config
{
    protected function readEnvLines(string $cwd, OutputInterface $output): ?array
    {
        $envPath = $cwd.'/.env';

        if (!file_exists($envPath)) {
            if (file_put_contents($envPath, $this->defaultEnvTemplate()) === false) {
                $output->writeln('<error>❌ Impossibile creare il file .env</error>');
                return null;
            }

            $output->writeln('<info>✅ File .env creato automaticamente.</info>');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            $output->writeln('<error>❌ Impossibile leggere il file .env</error>');
            return null;
        }

        return $lines;
    }

    protected function writeEnvLines(string $cwd, array $lines, OutputInterface $output, array $updatedKeys = []): bool
    {
        $envPath = $cwd.'/.env';

        if (file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            $output->writeln('<error>❌ Impossibile aggiornare il file .env</error>');
            return false;
        }

        if (count($updatedKeys) > 0) {
            $output->writeln('<info>✅ .env aggiornato: '.implode(', ', $this->redactUpdatedKeys($updatedKeys)).'</info>');
        }

        return true;
    }

    protected function completeEnvValues(array &$lines, array &$keyToIndex, array $values, bool $overwrite = false): array
    {
        $updatedKeys = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $existing = $this->envValue($lines, $keyToIndex, $key);

            if (!$overwrite && !$this->isMissingEnvValue($existing, $key)) {
                continue;
            }

            $this->setEnvValue($lines, $keyToIndex, $key, (string) $value);
            $updatedKeys[] = $key;
        }

        return $updatedKeys;
    }

    protected function loadEnv(string $cwd): void
    {
        $envPath = $cwd.'/.env';

        if (!file_exists($envPath)) {
            return;
        }

        Dotenv::createImmutable($cwd)->safeLoad();
    }

    protected function defaultEnvTemplate(): string
    {
        $adminName = RuntimeDefaults::adminName();
        $adminSurname = RuntimeDefaults::adminSurname();
        $adminEmail = RuntimeDefaults::adminEmail();
        $adminUsername = RuntimeDefaults::adminUsername();

        return <<<ENV
# App Info
APP_DEBUG=true
APP_DOMAIN=
APP_URL=
APP_KEY=

# Assets
ASSETS_VERSION=0.0

# Database
DB_HOSTNAME=
DB_USERNAME=
DB_PASSWORD=
DB_DATABASE=
DB_CHARSET=latin1
DB_COLLATION=latin1_swedish_ci
DB_CONNECTION_LOG=false

# Backend default user
USER_NAME=$adminName
USER_SURNAME=$adminSurname
USER_EMAIL=$adminEmail
USER_USERNAME=$adminUsername
USER_PASSWORD=
ENV;
    }

    protected function isMissingEnvValue(string $value, string $key): bool
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
            'DB_USERNAME' => ['tuo_db_user', 'db_user', 'username', 'sf8_test_user'],
            'DB_PASSWORD' => ['tua_db_pass', 'db_pass', 'password', 'sf8_test_password'],
            'DB_DATABASE' => ['db_name', 'my_database', 'main:myapp_sf8_test', 'myapp_sf8_test'],
            'USER_PASSWORD' => ['password', 'user_password'],
        ];

        if (isset($keySpecific[$key]) && in_array($normalized, $keySpecific[$key], true)) {
            return true;
        }

        return false;
    }

    protected function randomAlphaNumeric(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($alphabet) - 1;
        $output = '';

        for ($i = 0; $i < $length; $i++) {
            $output .= $alphabet[random_int(0, $max)];
        }

        return $output;
    }

    protected function parseHostPort(string $hostname, int $defaultPort = 0): array
    {
        $hostname = trim($hostname);

        if ($hostname === '') {
            return ['', $defaultPort];
        }

        if (preg_match('/^(.+):(\d+)$/', $hostname, $matches) === 1) {
            return [trim($matches[1]), (int) $matches[2]];
        }

        return [$hostname, $defaultPort];
    }

    protected function parseMainDatabase(string $database): string
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

            if (!str_contains($part, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $part, 2));

            if (strtolower($key) === 'main') {
                return $value;
            }
        }

        return str_contains($first, ':')
            ? trim((string) explode(':', $first, 2)[1])
            : $first;
    }

    protected function formatMainDatabase(string $databaseName, string $existingValue = ''): string
    {
        $databaseName = trim($databaseName);

        if ($databaseName === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', trim($existingValue)));
        $normalizedParts = [];
        $mainWritten = false;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (!str_contains($part, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $part, 2));
            $key = strtolower($key);

            if ($key === 'main') {
                $normalizedParts[] = 'main:'.$databaseName;
                $mainWritten = true;
                continue;
            }

            $normalizedParts[] = $key.':'.$value;
        }

        if (!$mainWritten) {
            array_unshift($normalizedParts, 'main:'.$databaseName);
        }

        return implode(', ', $normalizedParts);
    }

    protected function deriveDatabaseNameFromAppDomain(string $appDomain): string
    {
        $database = strtolower(trim($appDomain));
        $database = str_replace(['-', '.'], '_', $database);
        $database = preg_replace('/[^a-z0-9_]+/', '', $database);
        $database = preg_replace('/_+/', '_', (string) $database);
        $database = trim((string) $database, '_');

        return $database !== '' ? $database : 'app';
    }

    protected function buildLocalAppUrl(string $host, int $port): string
    {
        return 'http://'.$host.':'.$port;
    }

    protected function buildDefaultDbUsername(string $databaseName): string
    {
        $username = strtolower(trim($databaseName)).'_user';
        $username = preg_replace('/[^a-z0-9_]+/', '_', $username);
        $username = preg_replace('/_+/', '_', (string) $username);
        $username = trim((string) $username, '_');

        if ($username === '') {
            $username = 'app_user';
        }

        return substr($username, 0, 32);
    }

    protected function normalizeMysqlIdentifierOption(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        return $value;
    }

    protected function confirmAction(InputInterface $input, OutputInterface $output, string $question): bool
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>❌ Conferma richiesta. Riesegui in modalità interattiva oppure usa --force.</error>');
            return false;
        }

        $helper = $this->getHelper('question');
        $confirmation = new ConfirmationQuestion($question.' [y/N] ', false);

        return (bool) $helper->ask($input, $output, $confirmation);
    }

    protected function askPromptedValue(
        InputInterface $input,
        OutputInterface $output,
        string $key,
        string $question,
        string $default = '',
        bool $hidden = false
    ): string {
        if (!$input->isInteractive()) {
            $output->writeln('<error>❌ '.$key.' mancante. Passa il valore da CLI oppure esegui il comando in modalità interattiva.</error>');
            return '';
        }

        $helper = $this->getHelper('question');

        while (true) {
            try {
                $prompt = new Question($question.' ', $default !== '' ? $default : null);

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

            if ($default !== '') {
                return $default;
            }

            $output->writeln('<error>❌ '.$key.' obbligatorio.</error>');
        }
    }

    protected function validatePort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    protected function redactUpdatedKeys(array $updatedKeys): array
    {
        return array_map(
            fn ($key) => in_array($key, ['DB_PASSWORD', 'USER_PASSWORD', 'APP_KEY'], true) ? $key.'=***' : $key,
            $updatedKeys
        );
    }
}
