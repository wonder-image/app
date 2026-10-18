<?php
/** php tests/App/CredentialsGoogleCloudTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;

// Le variabili d'ambiente della macchina non devono falsare le prove.
foreach (['GCP_PROJECT_ID', 'GCP_API_KEY', 'GCP_CLIENT_API_KEY'] as $chiave) {
    unset($_ENV[$chiave]);
}

$credenziali = new class extends Credentials {
    public static function leggi(array $riga, bool $ripiego = true): object
    {
        $api = static::apiDefaults();
        static::googleCloud($api, $riga, $ripiego);

        return $api;
    }
};

$riga = [
    'gcp_project_id' => 'progetto-prova',
    'gcp_api_key' => 'chiave_privata_prova',
    'gcp_client_api_key' => '',
];

check('api(): la chiave pubblica vuota ricade sulla privata', function () use ($credenziali, $riga) {
    $api = $credenziali::leggi($riga);

    return $api->gcp_client_api_key === 'chiave_privata_prova'
        && $api->gcp_api_key === 'chiave_privata_prova'
        && $api->gcp_project_id === 'progetto-prova';
});

check('senza ripiego la chiave pubblica vuota resta vuota', function () use ($credenziali, $riga) {
    $api = $credenziali::leggi($riga, false);

    return $api->gcp_client_api_key === ''
        && $api->gcp_api_key === 'chiave_privata_prova'
        && $api->gcp_project_id === 'progetto-prova';
});

check('una chiave pubblica salvata vale con e senza ripiego', function () use ($credenziali, $riga) {
    $riga['gcp_client_api_key'] = 'chiave_pubblica_prova';

    return $credenziali::leggi($riga)->gcp_client_api_key === 'chiave_pubblica_prova'
        && $credenziali::leggi($riga, false)->gcp_client_api_key === 'chiave_pubblica_prova';
});

check('.env vince sulla riga anche senza ripiego', function () use ($credenziali, $riga) {
    $_ENV['GCP_CLIENT_API_KEY'] = 'chiave_pubblica_env';
    $_ENV['GCP_API_KEY'] = 'chiave_privata_env';

    try {
        $api = $credenziali::leggi($riga, false);

        return $api->gcp_client_api_key === 'chiave_pubblica_env'
            && $api->gcp_api_key === 'chiave_privata_env';
    } finally {
        unset($_ENV['GCP_CLIENT_API_KEY'], $_ENV['GCP_API_KEY']);
    }
});

check('storedApi(): solo la chiave pubblica cambia rispetto ad api()', function () {
    $_ENV['GCP_API_KEY'] = 'chiave_privata_env';

    try {
        $api = (array) Credentials::api();
        $salvate = Credentials::storedApi();
        $diverse = array_keys(array_diff_assoc(array_map('strval', $api), array_map('strval', $salvate)));

        return $api['gcp_client_api_key'] === 'chiave_privata_env'
            && $salvate['gcp_client_api_key'] === ''
            && $salvate['gcp_api_key'] === 'chiave_privata_env'
            && $diverse === ['gcp_client_api_key']
            && array_keys($api) === array_keys($salvate)
            && Credentials::api()->gcp_client_api_key === 'chiave_privata_env';
    } finally {
        unset($_ENV['GCP_API_KEY']);
    }
});

check('default: nessuna chiave Google', function () {
    $api = Credentials::apiDefaults();

    return $api->gcp_project_id === '' && $api->gcp_api_key === '' && $api->gcp_client_api_key === '';
});

summary();
