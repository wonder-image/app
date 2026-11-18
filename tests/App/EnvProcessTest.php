<?php
/** php tests/App/EnvProcessTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\App\EnvProcess;
use Wonder\App\Security\SecretCipher;

// Nomi usati dal test: li ripuliamo da $_ENV, $_SERVER e dall'ambiente del processo.
const ENV_PROCESS_TEST_KEYS = [
    'DB_HOSTNAME', 'DB_HOST', 'DB_USERNAME', 'DB_DATABASE', 'APP_ENCRYPTION_KEY',
    'API_KEY', 'HTTP_X_TEST_HEADER', 'FTP_PASSWORD', 'BWS_ACCESS_TOKEN', 'CLOUDFLARE_API_TOKEN',
    'NPM_GITHUB_TOKEN', 'ZZ_NOME_GENERICO',
];

function cleanEnvProcess(): void {
    foreach (ENV_PROCESS_TEST_KEYS as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    Credentials::resetEnv();
}

/** Cartella temporanea con un `.env` di prova (valori finti), e cwd spostata lì. */
function withDotenvFile(string $content, callable $fn): mixed {
    $dir = sys_get_temp_dir() . '/wonder-env-process-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir . '/.env', $content);
    $previous = getcwd();
    chdir($dir);

    try {
        return $fn();
    } finally {
        chdir($previous);
        unlink($dir . '/.env');
        rmdir($dir);
    }
}

check('isAllowed: nomi esatti e prefissi dell\'elenco chiuso', function () {
    return EnvProcess::isAllowed('API_KEY')
        && EnvProcess::isAllowed('DB_HOSTNAME')
        && EnvProcess::isAllowed('APP_ENCRYPTION_KEY')
        && EnvProcess::isAllowed('STRIPE_SECRET_KEY')
        && EnvProcess::isAllowed('FATTURE_IN_CLOUD_CLIENT_ID');
});

check('isAllowed: rifiuta richiesta HTTP, credenziali di deploy e nomi generici', function () {
    return !EnvProcess::isAllowed('')
        && !EnvProcess::isAllowed('HTTP_HOST')
        && !EnvProcess::isAllowed('PHP_AUTH_PW')
        && !EnvProcess::isAllowed('FTP_PASSWORD')
        && !EnvProcess::isAllowed('BWS_ACCESS_TOKEN')
        && !EnvProcess::isAllowed('CLOUDFLARE_API_TOKEN')
        && !EnvProcess::isAllowed('NPM_GITHUB_TOKEN')
        && !EnvProcess::isAllowed('PATH')
        && !EnvProcess::isAllowed('API_KEY_EXTRA')
        && !EnvProcess::isAllowed('XAPP_NAME');
});

check('variabile solo nell\'ambiente del processo (getenv, $_ENV vuoto) → arriva in $_ENV senza .env', function () {
    cleanEnvProcess();
    putenv('DB_HOSTNAME=host-dal-processo');

    return withDotenvFile("APP_ENV=production\n", function () {
        Credentials::loadEnv();

        return ($_ENV['DB_HOSTNAME'] ?? null) === 'host-dal-processo';
    });
});

check('chiave in $_SERVER (fastcgi_param) → arriva in $_ENV', function () {
    cleanEnvProcess();
    $_SERVER['DB_USERNAME'] = 'utente-da-fastcgi';

    return withDotenvFile("APP_ENV=production\n", function () {
        Credentials::loadEnv();

        return ($_ENV['DB_USERNAME'] ?? null) === 'utente-da-fastcgi';
    });
});

check('stessa chiave in .env e nell\'ambiente → vince l\'ambiente del processo', function () {
    cleanEnvProcess();
    putenv('DB_HOSTNAME=host-dal-processo');

    return withDotenvFile("DB_HOSTNAME=host-dal-file\nDB_DATABASE=db-dal-file\n", function () {
        Credentials::loadEnv();

        // Il resto del .env resta caricato normalmente.
        return ($_ENV['DB_HOSTNAME'] ?? null) === 'host-dal-processo'
            && ($_ENV['DB_DATABASE'] ?? null) === 'db-dal-file';
    });
});

check('valore vuoto nell\'ambiente = non impostato: il .env valorizza la chiave', function () {
    cleanEnvProcess();
    putenv('DB_HOSTNAME=');

    return withDotenvFile("DB_HOSTNAME=host-dal-file\n", function () {
        Credentials::loadEnv();

        return ($_ENV['DB_HOSTNAME'] ?? null) === 'host-dal-file';
    });
});

check('nulla impostato né nell\'ambiente né nel .env → la chiave resta assente', function () {
    cleanEnvProcess();

    return withDotenvFile("APP_ENV=production\n", function () {
        Credentials::loadEnv();

        return !array_key_exists('DB_HOSTNAME', $_ENV)
            && !Credentials::hasDatabaseCredentials();
    });
});

check('alias: DB_HOST dal processo è visto da hasDatabaseCredentials', function () {
    cleanEnvProcess();
    putenv('DB_HOST=host-dal-processo');
    putenv('DB_USERNAME=utente-dal-processo');
    putenv('DB_DATABASE=db-dal-processo');

    $result = withDotenvFile("APP_ENV=production\n", fn () => Credentials::hasDatabaseCredentials());
    putenv('DB_HOST');

    return $result === true;
});

check('nomi non ammessi NON vengono copiati in $_ENV', function () {
    cleanEnvProcess();
    putenv('FTP_PASSWORD=valore-finto');
    putenv('BWS_ACCESS_TOKEN=valore-finto');
    putenv('CLOUDFLARE_API_TOKEN=valore-finto');
    putenv('NPM_GITHUB_TOKEN=valore-finto');
    putenv('ZZ_NOME_GENERICO=valore-finto');
    $_SERVER['HTTP_X_TEST_HEADER'] = 'valore-finto';

    EnvProcess::hydrate();

    return !array_key_exists('FTP_PASSWORD', $_ENV)
        && !array_key_exists('BWS_ACCESS_TOKEN', $_ENV)
        && !array_key_exists('CLOUDFLARE_API_TOKEN', $_ENV)
        && !array_key_exists('NPM_GITHUB_TOKEN', $_ENV)
        && !array_key_exists('ZZ_NOME_GENERICO', $_ENV)
        && !array_key_exists('HTTP_X_TEST_HEADER', $_ENV);
});

check('mai sovrascrivere un valore non vuoto già in $_ENV', function () {
    cleanEnvProcess();
    $_ENV['API_KEY'] = 'gia-presente';
    putenv('API_KEY=dal-processo');

    EnvProcess::hydrate();

    return $_ENV['API_KEY'] === 'gia-presente';
});

check('SecretCipher::fromEnv trova APP_ENCRYPTION_KEY solo dall\'ambiente del processo', function () {
    cleanEnvProcess();
    $key = SecretCipher::generateKey();
    putenv('APP_ENCRYPTION_KEY=' . $key);

    $available = withDotenvFile("APP_ENV=production\n", function () {
        Credentials::loadEnv();

        return SecretCipher::fromEnv()->available();
    });

    return $available === SecretCipher::sodiumAvailable();
});

cleanEnvProcess();
summary();
