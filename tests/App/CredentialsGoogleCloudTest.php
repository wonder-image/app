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
    public static function leggi(array $riga): object
    {
        $api = static::apiDefaults();
        static::googleCloud($api, $riga);

        return $api;
    }
};

$riga = [
    'gcp_project_id' => 'progetto-prova',
    'gcp_api_key' => 'chiave_privata_prova',
    'gcp_client_api_key' => '',
];

check('la chiave pubblica vuota non ricade sulla privata', function () use ($credenziali, $riga) {
    $api = $credenziali::leggi($riga);

    return $api->gcp_client_api_key === ''
        && $api->gcp_api_key === 'chiave_privata_prova'
        && $api->gcp_project_id === 'progetto-prova';
});

check('una chiave pubblica salvata resta quella', function () use ($credenziali, $riga) {
    $riga['gcp_client_api_key'] = 'chiave_pubblica_prova';

    return $credenziali::leggi($riga)->gcp_client_api_key === 'chiave_pubblica_prova';
});

check('.env vince sulla riga', function () use ($credenziali, $riga) {
    $_ENV['GCP_CLIENT_API_KEY'] = 'chiave_pubblica_env';
    $_ENV['GCP_API_KEY'] = 'chiave_privata_env';

    try {
        $api = $credenziali::leggi($riga);

        return $api->gcp_client_api_key === 'chiave_pubblica_env'
            && $api->gcp_api_key === 'chiave_privata_env';
    } finally {
        unset($_ENV['GCP_CLIENT_API_KEY'], $_ENV['GCP_API_KEY']);
    }
});

check('con la sola chiave privata la pagina non riceve chiavi Google', function () {
    $_ENV['GCP_API_KEY'] = 'chiave_privata_env';

    try {
        $salvate = Credentials::storedApi();

        return Credentials::api()->gcp_client_api_key === ''
            && \Wonder\App\Support\GoogleMaps::apiKey() === ''
            && !\Wonder\App\Support\GoogleMaps::enabled()
            && $salvate['gcp_client_api_key'] === ''
            && $salvate['gcp_api_key'] === 'chiave_privata_env'
            && $salvate === (array) Credentials::api();
    } finally {
        unset($_ENV['GCP_API_KEY']);
    }
});

check('default: nessuna chiave Google', function () {
    $api = Credentials::apiDefaults();

    return $api->gcp_project_id === '' && $api->gcp_api_key === '' && $api->gcp_client_api_key === '';
});

summary();
