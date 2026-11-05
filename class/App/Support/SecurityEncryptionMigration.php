<?php

namespace Wonder\App\Support;

use mysqli;
use RuntimeException;
use Throwable;
use Wonder\App\Credentials;
use Wonder\App\Logger;
use Wonder\App\Models\Config\Security;
use Wonder\App\Security\SecretCipher;
use Wonder\App\Security\SecretCipherException;

/**
 * Cifra con `APP_ENCRYPTION_KEY` i segreti di `security` ancora in chiaro e
 * ricifra con la chiave corrente quelli scritti con
 * `APP_ENCRYPTION_KEY_PREVIOUS` (rotazione).
 *
 * Idempotente: un valore già cifrato con la chiave corrente non si tocca.
 * Senza chiave valida o senza sodium salta con un avviso nel log e non
 * genera mai una chiave. Un valore cifrato che non si apre (chiave
 * sconosciuta, alterato) resta com'è e finisce nel log con colonna e `kid`,
 * mai col valore. Ogni valore scritto si decifra di nuovo prima del salvataggio;
 * la riga si aggiorna in una transazione con `SELECT ... FOR UPDATE`.
 * Gira dopo le righe di seed, così trova la riga `id = 1`.
 */
final class SecurityEncryptionMigration
{
    public const TABLE = 'security';

    /** @return int colonne cifrate o ricifrate */
    public static function run(mysqli $connection, ?SecretCipher $cipher = null): int
    {
        $exists = $connection->query("SHOW TABLES LIKE '".self::TABLE."'");

        if (!is_object($exists) || (int) ($exists->num_rows ?? 0) === 0) {
            return 0;
        }

        $cipher ??= Credentials::secretCipher();

        try {
            $connection->begin_transaction();

            $result = $connection->query("SELECT * FROM `".self::TABLE."` WHERE `id` = 1 LIMIT 1 FOR UPDATE");
            $row = is_object($result) ? $result->fetch_assoc() : null;

            if (!is_array($row)) {
                $connection->rollback();

                return 0;
            }

            $plan = self::plan($row, $cipher);

            if ($plan['skipped']) {
                $connection->rollback();

                if ($plan['pending'] > 0) {
                    self::log('Segreti in chiaro non cifrati: '.SecretCipher::ENV_KEY.' assente o non valida, o sodium mancante.', 'WARNING', [ 'columns' => $plan['pending'] ]);
                }

                return 0;
            }

            foreach ($plan['unreadable'] as $column => $kid) {
                self::log('Segreto cifrato che non si decifra: lasciato com\'è.', 'WARNING', [ 'column' => $column, 'kid' => $kid ]);
            }

            if ($plan['updates'] === []) {
                $connection->rollback();

                return 0;
            }

            self::update($connection, $plan['updates']);
            $connection->commit();

            return count($plan['updates']);
        } catch (Throwable $exception) {
            try {
                $connection->rollback();
            } catch (Throwable) {
            }

            self::log('Cifratura dei segreti interrotta: nessuna colonna modificata ('.$exception::class.').', 'ERROR');

            return 0;
        }
    }

    /**
     * Cosa scrivere per una riga, senza toccare il database.
     *
     * @param array<string, mixed> $row
     * @return array{skipped: bool, pending: int, updates: array<string, string>, unreadable: array<string, string|null>}
     */
    public static function plan(array $row, SecretCipher $cipher): array
    {
        $plan = [ 'skipped' => false, 'pending' => 0, 'updates' => [], 'unreadable' => [] ];

        foreach (Security::SECRET_COLUMNS as $column) {
            $value = $row[$column] ?? null;

            if (!is_string($value) || $value === '') {
                continue;
            }

            if (!SecretCipher::isEncrypted($value)) {
                $plan['pending']++;
            }
        }

        if (!$cipher->available()) {
            $plan['skipped'] = true;

            return $plan;
        }

        foreach (Security::SECRET_COLUMNS as $column) {
            $value = $row[$column] ?? null;

            if (!is_string($value) || $value === '') {
                continue;
            }

            $context = Credentials::secretContext($column);

            if (SecretCipher::isEncrypted($value) && !$cipher->needsRotation($value)) {
                if (!$cipher->knows($value)) {
                    $plan['unreadable'][$column] = SecretCipher::kidOf($value);
                }

                continue;
            }

            try {
                $plaintext = $cipher->decrypt($value, $context);
            } catch (SecretCipherException) {
                $plan['unreadable'][$column] = SecretCipher::kidOf($value);
                continue;
            }

            $encrypted = $cipher->encrypt($plaintext, $context);

            if (!hash_equals($plaintext, $cipher->decrypt($encrypted, $context))) {
                throw new RuntimeException('Verifica della cifratura fallita.');
            }

            $plan['updates'][$column] = $encrypted;
        }

        return $plan;
    }

    /** @param array<string, string> $updates */
    private static function update(mysqli $connection, array $updates): void
    {
        $sets = [];

        foreach (array_keys($updates) as $column) {
            $sets[] = '`'.str_replace('`', '``', $column).'` = ?';
        }

        $statement = $connection->prepare("UPDATE `".self::TABLE."` SET ".implode(', ', $sets)." WHERE `id` = 1");

        if ($statement === false) {
            throw new RuntimeException('UPDATE non preparato.');
        }

        $values = array_values($updates);
        $statement->bind_param(str_repeat('s', count($values)), ...$values);

        if (!$statement->execute()) {
            $statement->close();

            throw new RuntimeException('UPDATE non eseguito.');
        }

        $statement->close();
    }

    private static function log(string $message, string $level, array $context = []): void
    {
        try {
            Logger::log(new RuntimeException($message), 'update', 'security-encryption', $level, 'error', $context, false);
        } catch (Throwable) {
        }
    }
}
