<?php
/** php tests/App/GoogleTagManagerCredentialsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\App\Integrations\Core\GoogleTagManager;
use Wonder\App\Integrations\Integrations;
use Wonder\App\Integrations\IntegrationStatus;
use Wonder\App\Support\AnalyticsToCredentialsMigration;

$root = dirname(__DIR__, 2);

unset($_ENV['G_TAG_MANAGER_ID'], $_ENV['G_TAG_MANAGER_ACTIVE']);

check('Integrazione registrata in Credenziali', fn () =>
    Integrations::find('google-tag-manager') instanceof GoogleTagManager);

check('Stato: senza ID non connessa, con ID connessa', function () {
    $gtm = new GoogleTagManager();
    $empty = $gtm->status(['g_tag_manager_id' => '', 'g_tag_manager_active' => 'true']);
    $set = $gtm->status(['g_tag_manager_id' => 'GTM-ABC123', 'g_tag_manager_active' => 'true']);

    return $empty->state === IntegrationStatus::DISCONNECTED
        && $set->state === IntegrationStatus::CONNECTED && $set->note === null;
});

check('Stato: spento resta connessa con la nota «Spento»', fn () =>
    (new GoogleTagManager())->status(['g_tag_manager_id' => 'GTM-ABC123', 'g_tag_manager_active' => 'false'])->note === 'Spento');

check('active(): vuoto e true accesi, false spento', fn () =>
    GoogleTagManager::active('') && GoogleTagManager::active('true') && GoogleTagManager::active(null)
    && !GoogleTagManager::active('false'));

check('Credentials: default vuoto e acceso', function () {
    $api = (new class extends Credentials {
        public static function defaults(): object { return static::apiDefaults(); }
    })::defaults();

    return $api->g_tag_manager_id === '' && $api->g_tag_manager_active === 'true';
});

$head = (string) file_get_contents($root.'/app/view/components/frontend/layout/head.php');
check('head.php: GTM letto da Credentials, nessuna lettura della tabella analytics', fn () =>
    str_contains($head, 'Credentials::api()') && str_contains($head, 'g_tag_manager_id')
    && !str_contains($head, "sqlSelect('analytics'"));

check('Pagina Analitica rimossa', fn () =>
    !class_exists('Wonder\\App\\Resources\\Config\\AnalyticsResource')
    && !class_exists('Wonder\\App\\Models\\Config\\Analytics'));

/** Connessione finta: tabelle e righe in memoria, registra gli UPDATE. */
function gtmConnection(array $analytics, array $security, bool $hasAnalytics = true): mysqli
{
    return new class($analytics, $security, $hasAnalytics) extends mysqli {
        public array $updates = [];
        public function __construct(public array $analytics, public array $security, public bool $hasAnalytics) {}
        public function real_escape_string($string): string { return addslashes((string) $string); }
        #[\ReturnTypeWillChange]
        public function query($query, $resultMode = MYSQLI_STORE_RESULT)
        {
            if (str_starts_with($query, "SHOW TABLES LIKE 'analytics'")) { return (object) ['num_rows' => $this->hasAnalytics ? 1 : 0]; }
            if (str_starts_with($query, 'SHOW')) { return (object) ['num_rows' => 1]; }
            $row = str_contains($query, '`analytics`') ? $this->analytics : $this->security;
            return new class($row) { public function __construct(private array $row) {} public function fetch_assoc(): array { return $this->row; } };
        }
        #[\ReturnTypeWillChange]
        public function prepare($query)
        {
            $connection = $this;
            return new class($connection) {
                private array $values = [];
                public function __construct(private $connection) {}
                public function bind_param($types, &...$values): bool { $this->values = $values; return true; }
                public function execute(): bool {
                    [$id, $active] = $this->values;
                    $this->connection->security = ['g_tag_manager_id' => $id, 'g_tag_manager_active' => $active];
                    $this->connection->updates[] = $this->values;
                    return true;
                }
                public function close(): bool { return true; }
            };
        }
    };
}

check('Migrazione: copia ID e stato, vuoto vale acceso, poi non riscrive', function () {
    $connection = gtmConnection(['tag_manager' => 'GTM-OLD1', 'active_tag_manager' => ''], ['g_tag_manager_id' => '']);
    $first = AnalyticsToCredentialsMigration::run($connection);
    $second = AnalyticsToCredentialsMigration::run($connection);

    return $first === 1 && $second === 0 && count($connection->updates) === 1
        && $connection->security === ['g_tag_manager_id' => 'GTM-OLD1', 'g_tag_manager_active' => 'true'];
});

check('Migrazione: non tocca un ID già in Credenziali', function () {
    $connection = gtmConnection(['tag_manager' => 'GTM-OLD1', 'active_tag_manager' => 'false'], ['g_tag_manager_id' => 'GTM-NEW2']);

    return AnalyticsToCredentialsMigration::run($connection) === 0 && $connection->updates === [];
});

check('Migrazione: spento resta spento', function () {
    $connection = gtmConnection(['tag_manager' => 'GTM-OLD1', 'active_tag_manager' => 'false'], ['g_tag_manager_id' => '']);
    AnalyticsToCredentialsMigration::run($connection);

    return $connection->security['g_tag_manager_active'] === 'false';
});

check('Migrazione: salta senza tabella analytics o senza ID', fn () =>
    AnalyticsToCredentialsMigration::run(gtmConnection(['tag_manager' => 'GTM-OLD1'], ['g_tag_manager_id' => ''], false)) === 0
    && AnalyticsToCredentialsMigration::run(gtmConnection(['tag_manager' => ''], ['g_tag_manager_id' => ''])) === 0);

summary();
