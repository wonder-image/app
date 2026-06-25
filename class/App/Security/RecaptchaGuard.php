<?php

namespace Wonder\App\Security;

use RuntimeException;
use Throwable;
use Wonder\App\Logger;
use Wonder\Plugin\Google\Credentials as GoogleCredentials;
use Wonder\Plugin\Google\Security\reCAPTCHA;

/**
 * Verifica server-side first-class di reCAPTCHA (Enterprise).
 *
 * È l'unica sede del comportamento di verifica: legge token/action dalla
 * request, valida l'action attesa, chiama l'engine low-level
 * {@see reCAPTCHA::verify()}, e in caso di fallimento logga in modo
 * strutturato e lancia una `RuntimeException` con codice utente `617`
 * (`__t('notifications.617.text')`). Nessun consumer deve più riscrivere
 * try/catch, controllo `valid`, logging o throw a mano.
 *
 * Uso idiomatico nei Resource:
 *
 *   static::verifyRecaptcha('contact');   // delega qui
 *
 * Uso diretto fuori dai Resource:
 *
 *   RecaptchaGuard::for('contact')->verify();          // throw 617 su fallimento
 *   if (RecaptchaGuard::for('contact')->passes()) {…}  // variante non-throwing
 *
 * L'engine è iniettabile via {@see withEngine()} per i test (qualunque
 * oggetto con `verify(string $token, string $action): object`), così la
 * verifica è testabile senza HTTP reale.
 */
final class RecaptchaGuard
{
    /** Codice utente i18n del captcha non valido. */
    public const ERROR_CODE = 617;

    private const TOKEN_KEY = 'g-recaptcha-token';
    private const ACTION_KEY = 'g-recaptcha-action';

    /** Action attesa; stringa vuota = accetta qualunque action ricevuta. */
    private string $expectedAction;

    /** Sorgente request; null = lazy `$_POST`. */
    private ?array $request = null;

    /** Engine low-level; null = lazy `new reCAPTCHA()`. */
    private ?object $engine = null;

    /** Etichetta `service` nel logger. */
    private string $service = 'recaptcha';

    /** Etichetta `action` nel logger. */
    private string $logAction = 'verify';

    /** File di log relativo a `storage/logs/`. */
    private string $logFile = 'error/recaptcha';

    private function __construct(string $expectedAction)
    {
        $this->expectedAction = trim($expectedAction);
    }

    /**
     * Crea un guard per l'action attesa (es. `contact`, `submit`).
     * Passa stringa vuota solo per accettare qualunque action (BC legacy).
     */
    public static function for(string $expectedAction): self
    {
        return new self($expectedAction);
    }

    /** Sorgente dei campi `g-recaptcha-*` (default `$_POST`). Iniettabile per i test. */
    public function withRequest(array $request): self
    {
        $this->request = $request;

        return $this;
    }

    /** Engine low-level alternativo (`verify($token,$action): object`). Default `new reCAPTCHA()`. */
    public function withEngine(object $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    /** Etichetta `service` nel logger (es. `resource:site-request`). */
    public function withService(string $service): self
    {
        $service = trim($service);

        if ($service !== '') {
            $this->service = $service;
        }

        return $this;
    }

    /** Etichetta `action` nel logger. */
    public function withLogAction(string $action): self
    {
        $action = trim($action);

        if ($action !== '') {
            $this->logAction = $action;
        }

        return $this;
    }

    /** File di log relativo a `storage/logs/` (default `error/recaptcha`). */
    public function withLogFile(string $file): self
    {
        $file = trim($file);

        if ($file !== '') {
            $this->logFile = $file;
        }

        return $this;
    }

    /**
     * Esegue la verifica. Non ritorna nulla in caso di successo; su qualunque
     * fallimento logga e lancia `RuntimeException(__t('notifications.617.text'), 617)`.
     */
    public function verify(): void
    {
        $request = $this->request ?? $_POST;

        $token = trim((string) ($request[self::TOKEN_KEY] ?? ''));
        $action = trim((string) ($request[self::ACTION_KEY] ?? ''));

        if ($token === '' || $action === '') {
            $this->fail('missing', [
                'has_token' => $token !== '',
                'has_action' => $action !== '',
            ]);
        }

        if ($this->expectedAction !== '' && $action !== $this->expectedAction) {
            $this->fail('action_mismatch', [
                'expected' => $this->expectedAction,
                'received' => $action,
            ]);
        }

        $expected = $this->expectedAction !== '' ? $this->expectedAction : $action;

        try {
            $engine = $this->engine ?? new reCAPTCHA();
            $result = $engine->verify($token, $expected);
        } catch (Throwable $exception) {
            $this->fail('engine_error', ['expected' => $expected], $exception);
        }

        if (empty($result->valid)) {
            $this->fail('invalid', [
                'expected' => $expected,
                'google_result' => (array) ($result->result ?? []),
            ]);
        }
    }

    /**
     * Variante non-throwing: ritorna `false` invece di lanciare il 617.
     * Qualsiasi altra eccezione (configurazione, ecc.) viene propagata.
     */
    public function passes(): bool
    {
        try {
            $this->verify();

            return true;
        } catch (RuntimeException $exception) {
            if ($exception->getCode() === self::ERROR_CODE) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Punto unico di logging + throw. Logga l'eccezione causa (se presente,
     * tipicamente un errore dell'engine) altrimenti la 617 stessa, poi lancia.
     *
     * @param array<string,mixed> $context
     * @return never
     */
    private function fail(string $reason, array $context = [], ?Throwable $cause = null): void
    {
        $exception = new RuntimeException($this->userMessage(), self::ERROR_CODE, $cause);

        Logger::log(
            $cause ?? $exception,
            $this->service,
            $this->logAction,
            $reason === 'engine_error' ? 'ERROR' : 'WARNING',
            $this->logFile,
            array_merge($this->baseContext(), ['reason' => $reason], $context),
            false
        );

        throw $exception;
    }

    /** Messaggio utente i18n; tollera l'assenza di `__t()` (es. in test). */
    private function userMessage(): string
    {
        if (function_exists('__t')) {
            $message = (string) __t('notifications.'.self::ERROR_CODE.'.text');

            if (trim($message) !== '') {
                return $message;
            }
        }

        return 'reCAPTCHA verification failed';
    }

    /** @return array<string,mixed> */
    private function baseContext(): array
    {
        $context = [
            'expected_action' => $this->expectedAction,
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        // Presenza credenziali: utile per diagnosticare misconfigurazioni
        // senza loggare i valori. Best-effort, non deve mai far fallire il log.
        try {
            $credentials = GoogleCredentials::get();
            $context['has_gcp_project_id'] = trim((string) ($credentials->gcp_project_id ?? '')) !== '';
            $context['has_gcp_api_key'] = trim((string) ($credentials->gcp_api_key ?? '')) !== '';
            $context['has_site_key'] = trim((string) ($credentials->recaptcha_site_key ?? '')) !== '';
        } catch (Throwable) {
            // credenziali non disponibili nel contesto corrente: si ignora.
        }

        return $context;
    }
}
