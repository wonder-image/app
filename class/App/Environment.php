<?php

namespace Wonder\App;

use Wonder\App\Security\SecretCipher;

/**
 * Ambiente dell'applicazione letto da `APP_ENV`: `local`, `staging` o
 * `production`.
 *
 * Il valore è obbligatorio. Se manca o non è valido `current()` lancia
 * `EnvironmentException`: non si ripiega più su `production`, perché un
 * ambiente sbagliato in silenzio decide debug, tabelle `localOnly()` e
 * controlli di sicurezza. Il controllo scatta all'avvio (`bootGuard()`, chiamato
 * da `wonder-image.php`). Il `.env` appartiene al sito: `forge start` scrive
 * `APP_ENV=local`, il deploy scrive `staging` o `production`.
 *
 * Dove non è deciso diversamente lo staging si comporta come la produzione:
 * debug spento senza `APP_DEBUG`, tabelle `localOnly()` in sola lettura,
 * reCAPTCHA e throttle del login con i limiti di produzione. Solo `local` è
 * permissivo, quindi i controlli si scrivono come `isLocal()`/`!isLocal()`.
 */
final class Environment
{
    public const LOCAL = 'local';
    public const STAGING = 'staging';
    public const PRODUCTION = 'production';

    /** Valori ammessi per `APP_ENV`. */
    public const ALLOWED = [self::LOCAL, self::STAGING, self::PRODUCTION];

    private static ?string $current = null;

    /** L'avviso sulla chiave di cifratura si scrive una volta per processo. */
    private static bool $encryptionKeyWarned = false;

    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        // Il valore sta nel .env del sito: senza questo caricamento una
        // richiesta web che chiede l'ambiente presto (es. registrazione delle
        // route) memorizzerebbe "production" anche in locale.
        if (class_exists(Credentials::class)) {
            Credentials::loadEnv();
        }

        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
        $value = is_string($value) ? strtolower(trim($value)) : '';

        if (!in_array($value, self::ALLOWED, true)) {
            throw new EnvironmentException(self::message($value));
        }

        return self::$current = $value;
    }

    /** Messaggio d'errore: valore trovato, valori ammessi, dove impostarlo. */
    private static function message(string $value): string
    {
        $found = $value === '' ? 'assente o vuoto' : '"'.substr($value, 0, 40).'"';

        return 'APP_ENV non valido: trovato '.$found.'; i valori ammessi sono '
            .implode(', ', self::ALLOWED)
            .'. Imposta APP_ENV nel file .env del sito (local in locale con `php forge start`, staging in staging, production in produzione).';
    }

    /**
     * Controllo all'avvio: lancia `EnvironmentException` se `APP_ENV` manca o
     * non è valido. Non memorizza nulla in caso di errore.
     */
    public static function assertConfigured(): string
    {
        return self::current();
    }

    /**
     * Controllo di avvio per `wonder-image.php`. Se `APP_ENV` non va bene
     * scrive una riga in `error_log` e chiude: via web con un 500 generico
     * (nessun dettaglio a schermo, JSON sotto `/api/`), da riga di comando con
     * il messaggio su STDERR e codice di uscita 1.
     *
     * `$terminate` e `$sapi` servono ai test; di default chiude con `exit` e
     * usa `PHP_SAPI`.
     *
     * Se `APP_ENV` è valido, fuori da `local` e via web controlla anche la
     * chiave di cifratura: vedi `warnIfEncryptionKeyMissing()` (solo avviso).
     */
    public static function bootGuard(?callable $terminate = null, ?string $sapi = null): void
    {
        try {
            self::assertConfigured();
            self::warnIfEncryptionKeyMissing($sapi);

            return;
        } catch (EnvironmentException $exception) {
            $message = $exception->getMessage();
        }

        @error_log('[wonder-image] '.$message);
        $terminate ??= static function (int $code): never {
            exit($code);
        };

        if (($sapi ?? PHP_SAPI) === 'cli') {
            fwrite(STDERR, $message.PHP_EOL);
            $terminate(1);

            return;
        }

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
        }

        if (str_starts_with($path, '/api/')) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode(['success' => false, 'status' => 500, 'response' => 'Errore interno.'], JSON_UNESCAPED_UNICODE);
        } else {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }

            echo 'Errore interno.';
        }

        $terminate(1);
    }

    /**
     * Avviso, non errore: fuori da `local` e via web, se `APP_ENCRYPTION_KEY`
     * manca o non è valida scrive una riga in `error_log` (una volta per
     * processo). Senza la chiave i segreti cifrati nel database (`enc:v1:`) non
     * si leggono e quelli nuovi si salvano in chiaro. Non apre il database e non
     * scrive mai il valore della chiave. Ritorna `true` se ha scritto l'avviso.
     *
     * Da riga di comando non scrive nulla (cron e `forge` stamperebbero
     * l'avviso a ogni esecuzione). `$sapi` serve ai test.
     */
    public static function warnIfEncryptionKeyMissing(?string $sapi = null): bool
    {
        if (self::$encryptionKeyWarned || ($sapi ?? PHP_SAPI) === 'cli' || self::isLocal()) {
            return false;
        }

        if (SecretCipher::fromEnv()->currentKid() !== null) {
            return false;
        }

        self::$encryptionKeyWarned = true;

        @error_log('[wonder-image] '.SecretCipher::ENV_KEY.' assente o non valida: i segreti cifrati nel database (enc:v1:) non si leggono e quelli nuovi si salvano in chiaro. Imposta la chiave (64 caratteri esadecimali) nell\'ambiente del processo o nel .env.');

        return true;
    }

    public static function isLocal(): bool
    {
        return self::current() === self::LOCAL;
    }

    public static function isStaging(): bool
    {
        return self::current() === self::STAGING;
    }

    public static function isProduction(): bool
    {
        return self::current() === self::PRODUCTION;
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$current = null;
        self::$encryptionKeyWarned = false;
    }
}
