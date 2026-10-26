<?php
/** php tests/Auth/UserEmailChangeTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
$ROOT = sys_get_temp_dir();
require __DIR__.'/../../app/function/function.php';

use Wonder\App\Support\RequestMemo;

/** mysqli senza connessione: registra le query e risponde con righe preparate. */
final class EmailChangeFakeMysqli extends mysqli
{
    /** @var string[] */
    public array $log = [];

    /** @var array<string, list<array<string, mixed>>> prefisso query => righe */
    public array $rows = [];

    public function __construct() {}

    public function real_escape_string(string $string): string
    {
        return addslashes($string);
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT)
    {
        $this->log[] = $query;

        foreach ($this->rows as $prefix => $rows) {
            if (str_starts_with($query, $prefix)) {
                return new EmailChangeFakeResult($rows);
            }
        }

        return str_starts_with($query, 'SELECT') || str_starts_with($query, 'SHOW') ? new EmailChangeFakeResult([]) : true;
    }
}

final class EmailChangeFakeResult
{
    public int $num_rows;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows)
    {
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array { return $this->rows[0] ?? null; }

    public function fetch_all(int $mode = MYSQLI_NUM): array { return $this->rows; }

    public function free(): void {}
}

/** Utente 7 verificato; la SELECT per id lo restituisce, quella di unicità no. */
function emailChangeFresh(string $storedEmail = 'mario@example.com'): EmailChangeFakeMysqli {
    RequestMemo::reset();
    $GLOBALS['ALERT'] = null;
    $GLOBALS['mysqli'] = new EmailChangeFakeMysqli();
    $GLOBALS['DEFAULT'] = (object) ['colorUser' => ['blue' => ['color' => '#00f', 'contrast' => '#fff']]];
    $GLOBALS['PATH'] = (object) ['upload' => '/upload'];
    $GLOBALS['mysqli']->rows["SELECT * FROM `user` WHERE id = '7'"] = [[
        'id' => '7', 'name' => 'Mario', 'surname' => 'Rossi', 'email' => $storedEmail,
        'authority' => '[]', 'area' => '["frontend"]', 'color' => '', 'deleted' => 'false', 'active' => 'true',
        'email_verified' => '1', 'email_verified_at' => '2026-10-01 10:00:00',
    ]];

    return $GLOBALS['mysqli'];
}

function emailChangeUpdates(EmailChangeFakeMysqli $mysqli): array {
    return array_values(array_filter($mysqli->log, static fn (string $q): bool => str_starts_with($q, 'UPDATE `user`')));
}

check('lo staff cambia l\'email: la stessa UPDATE azzera email_verified', function () {
    $mysqli = emailChangeFresh();
    user(['email' => 'nuova@example.com'], 7);
    $updates = emailChangeUpdates($mysqli);

    return empty($GLOBALS['ALERT'])
        && count($updates) === 1
        && str_contains($updates[0], "`email` = 'nuova@example.com'")
        && preg_match("/`email_verified` = '?0'?/", $updates[0]) === 1
        && preg_match('/`email_verified_at` = NULL/i', $updates[0]) === 1;
});

check('un email_verified nel POST non salva un\'email nuova come verificata', function () {
    $mysqli = emailChangeFresh();
    user(['email' => 'nuova@example.com', 'email_verified' => '1'], 7);
    $updates = emailChangeUpdates($mysqli);

    return count($updates) === 1 && preg_match("/`email_verified` = '?0'?/", $updates[0]) === 1;
});

check('stessa email (maiuscole e spazi diversi): la verifica resta', function () {
    $mysqli = emailChangeFresh();
    user(['email' => ' Mario@Example.com '], 7);
    $updates = emailChangeUpdates($mysqli);

    return count($updates) === 1 && !str_contains($updates[0], 'email_verified');
});

check('stessa email con apostrofo, salvata grezza o sanificata: la verifica resta', function () {
    foreach (["o'brien@example.com", "o\\'brien@example.com"] as $stored) {
        $mysqli = emailChangeFresh($stored);
        user(['email' => "O'Brien@example.com"], 7);
        $updates = emailChangeUpdates($mysqli);
        if (count($updates) !== 1 || str_contains($updates[0], 'email_verified')) {
            return false;
        }
    }

    return true;
});

check('modifica senza email: la verifica resta', function () {
    $mysqli = emailChangeFresh();
    user(['name' => 'Luigi'], 7);
    $updates = emailChangeUpdates($mysqli);

    return count($updates) === 1 && !str_contains($updates[0], 'email_verified');
});

check('email cambiata: i link di verifica ancora aperti vengono revocati', function () {
    $mysqli = emailChangeFresh();
    $mysqli->rows['SHOW TABLES LIKE'] = [['consent_confirmation_tokens']];
    $mysqli->rows['SELECT * FROM `consent_confirmation_tokens`'] = [['id' => '3', 'user_id' => '7']];
    user(['email' => 'nuova@example.com'], 7);
    $revoked = array_filter($mysqli->log, static fn (string $q): bool => str_starts_with($q, 'UPDATE `consent_confirmation_tokens`')
        && str_contains($q, '`revoked_at`') && str_contains($q, "`id` = '3'"));

    return count($revoked) === 1;
});

summary();
