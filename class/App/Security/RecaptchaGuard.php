<?php

namespace Wonder\App\Security;

use RuntimeException;
use Throwable;
use Wonder\App\Environment;
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
 * oggetto con `verify(string $token, string $action, ?string $siteKey): object`), così la
 * verifica è testabile senza HTTP reale.
 *
 * v3 (punteggio): il campo `g-recaptcha-type` sceglie la chiave (`score` o
 * `checkbox`, assente = `checkbox`). Punteggio sotto {@see minScore()} con la
 * chiave a casella configurata → segno in sessione e {@see RecaptchaChallenge}
 * (618: il form mostra la casella); senza chiave a casella → 617. Se l'engine
 * lancia o Google non risponde (rete, 429, 5xx) la verifica si salta
 * (fail-open) con una riga di log; con {@see failClosed()} attivo dà invece
 * 617. Un 401/403 di Google è un errore di configurazione (chiave API o
 * progetto): 617, mai salto. Con `APP_ENV=local` la verifica si salta sempre,
 * con una riga di log.
 */
final class RecaptchaGuard
{
    /** Codice utente i18n del captcha non valido. */
    public const ERROR_CODE = 617;

    private const TOKEN_KEY = 'g-recaptcha-token';
    private const ACTION_KEY = 'g-recaptcha-action';
    private const TYPE_KEY = 'g-recaptcha-type';

    /** Token più lunghi o con caratteri fuori da ASCII stampabile non vanno a Google: 617. */
    private const TOKEN_MAX_LENGTH = 8192;

    /** Sessione: azioni per cui il form deve mostrare la casella. */
    private const CHALLENGE_SESSION = 'wi_recaptcha_challenge';

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

    /** Punteggio v3 minimo. */
    private float $minScore = 0.5;

    /** Chiavi `checkbox`/`score`; null = lazy dalle credenziali. */
    private ?array $siteKeys = null;

    /** Google non raggiungibile → 617 invece del salto; null = `Environment::isProduction()`. */
    private ?bool $failClosed = false;

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

    /** Engine low-level alternativo (`verify($token, $action, $siteKey): object`). Default `new reCAPTCHA()`. */
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

    /** Punteggio v3 minimo (default 0.5). */
    public function minScore(float $score): self
    {
        $this->minScore = $score;

        return $this;
    }

    /** Chiavi del sito (default dalle credenziali). Iniettabili per i test. */
    public function withSiteKeys(string $checkbox, string $score): self
    {
        $this->siteKeys = ['checkbox' => trim($checkbox), 'score' => trim($score)];

        return $this;
    }

    /**
     * Google non raggiungibile (eccezione, rete, 429, 5xx) → 617 invece del
     * salto. Senza argomento vale solo in produzione (letto al `verify()`).
     */
    public function failClosed(?bool $value = null): self
    {
        $this->failClosed = $value;

        return $this;
    }

    /** Almeno una chiave reCAPTCHA configurata. */
    public static function configured(): bool
    {
        $keys = self::credentialKeys();

        return $keys['checkbox'] !== '' || $keys['score'] !== '';
    }

    /** L'errore è un fallimento della verifica (617) o la richiesta della casella (618). */
    public static function isFailure(Throwable $error): bool
    {
        return $error instanceof RuntimeException
            && in_array($error->getCode(), [self::ERROR_CODE, RecaptchaChallenge::CHALLENGE_CODE], true);
    }

    /** Il form di `$action` deve mostrare la casella? Legge e cancella il segno; false senza sessione. */
    public static function challenged(string $action): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $action = trim($action);
        $open = ($_SESSION[self::CHALLENGE_SESSION][$action] ?? false) === true;
        unset($_SESSION[self::CHALLENGE_SESSION][$action]);

        return $open;
    }

    /**
     * Esegue la verifica. Non ritorna nulla in caso di successo (o di salto per
     * Google non disponibile); lancia `RuntimeException(617)` o
     * `RecaptchaChallenge(618)`.
     */
    public function verify(): void
    {
        if (Environment::isLocal()) {
            $this->skip('local_env', 'INFO', []);

            return;
        }

        $request = $this->request ?? $_POST;

        $token = trim((string) ($request[self::TOKEN_KEY] ?? ''));
        $action = trim((string) ($request[self::ACTION_KEY] ?? ''));
        $type = trim((string) ($request[self::TYPE_KEY] ?? ''));
        $type = $type === '' ? 'checkbox' : $type;

        if ($token === '' || $action === '') {
            $this->fail('missing', [
                'has_token' => $token !== '',
                'has_action' => $action !== '',
                'type' => $type,
            ]);
        }

        if (strlen($token) > self::TOKEN_MAX_LENGTH || preg_match('/^[\x21-\x7E]+$/', $token) !== 1) {
            $this->fail('invalid', ['malformed' => true, 'length' => strlen($token), 'type' => $type]);
        }

        $keys = $this->siteKeys();

        if (!isset($keys[$type]) || $keys[$type] === '') {
            $this->fail('invalid', ['type' => $type, 'type_configured' => false]);
        }

        if ($this->expectedAction !== '' && $action !== $this->expectedAction) {
            $this->fail('action_mismatch', [
                'expected' => $this->expectedAction,
                'received' => $action,
                'type' => $type,
            ]);
        }

        $expected = $this->expectedAction !== '' ? $this->expectedAction : $action;

        try {
            $engine = $this->engine ?? new reCAPTCHA();
            $result = $engine->verify($token, $expected, $keys[$type]);
        } catch (Throwable $exception) {
            if ($this->closed()) {
                $this->fail('engine_error', ['expected' => $expected, 'type' => $type, 'fail_closed' => true], $exception, null, 'ERROR');
            }

            $this->skip('engine_error', 'ERROR', ['expected' => $expected, 'type' => $type], $exception);

            return;
        }

        $httpCode = (int) ($result->httpCode ?? 200);
        $context = ['expected' => $expected, 'type' => $type, 'http_code' => $httpCode];

        // Chiave API o progetto GCP sbagliati: errore di configurazione, non indisponibilità.
        if (in_array($httpCode, [401, 403], true)) {
            $this->fail('config', $context, null, null, 'ERROR');
        }

        if (($result->available ?? true) === false) {
            if ($this->closed()) {
                $this->fail('engine_unavailable', $context + ['fail_closed' => true], null, null, 'ERROR');
            }

            $level = in_array($httpCode, [0, 200, 429], true) || $httpCode >= 500 ? 'WARNING' : 'ERROR';
            $this->skip('engine_unavailable', $level, $context);

            return;
        }

        if (empty($result->valid)) {
            $google = (array) ($result->result ?? []);
            unset($google['event']);
            $this->fail('invalid', $context + ['google_result' => $google]);
        }

        $googleAction = trim((string) ($result->action ?? ''));

        if ($googleAction !== '' && $googleAction !== $expected) {
            $this->fail('action_mismatch', $context + ['received' => $googleAction, 'source' => 'google']);
        }

        if ($type === 'score') {
            $score = isset($result->score) && is_numeric($result->score) ? (float) $result->score : null;

            if ($score === null || $score < $this->minScore) {
                $context['score'] = $score;

                if ($keys['checkbox'] !== '') {
                    $this->markChallenge($expected);
                    $this->fail('low_score', $context + ['challenge' => true], null, new RecaptchaChallenge());
                }

                $this->fail('low_score', $context);
            }
        }

        $this->clearChallenge($expected);
    }

    /**
     * Variante non-throwing: `false` invece del 617 o del 618.
     * Qualsiasi altra eccezione (configurazione, ecc.) viene propagata.
     */
    public function passes(): bool
    {
        try {
            $this->verify();

            return true;
        } catch (RuntimeException $exception) {
            if (!self::isFailure($exception)) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * Punto unico di logging + throw. Logga l'eccezione causa (se presente)
     * altrimenti quella che sta per lanciare (617 o 618), poi lancia.
     *
     * @param array<string,mixed> $context
     * @param RuntimeException|null $error eccezione da lanciare al posto del 617
     * @return never
     */
    private function fail(string $reason, array $context = [], ?Throwable $cause = null, ?RuntimeException $error = null, string $level = 'WARNING'): void
    {
        $exception = $error ?? new RuntimeException($this->userMessage(), self::ERROR_CODE, $cause);

        Logger::log(
            $cause ?? $exception,
            $this->service,
            $this->logAction,
            $level,
            $this->logFile,
            array_merge($this->baseContext(), ['reason' => $reason], $context),
            false
        );

        throw $exception;
    }

    /** Verifica saltata (fail-open): solo log, nessuna eccezione. */
    private function skip(string $reason, string $level, array $context, ?Throwable $cause = null): void
    {
        Logger::log(
            $cause ?? new RuntimeException('reCAPTCHA verification skipped'),
            $this->service,
            $this->logAction,
            $level,
            $this->logFile,
            array_merge($this->baseContext(), ['reason' => $reason, 'skipped' => true], $context),
            false
        );
    }

    private function closed(): bool
    {
        return $this->failClosed ?? Environment::isProduction();
    }

    private function markChallenge(string $action): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::CHALLENGE_SESSION][$action] = true;
        }
    }

    private function clearChallenge(string $action): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::CHALLENGE_SESSION][$action]);
        }
    }

    /** @return array{checkbox: string, score: string} */
    private function siteKeys(): array
    {
        return $this->siteKeys ??= self::credentialKeys();
    }

    /** @return array{checkbox: string, score: string} */
    private static function credentialKeys(): array
    {
        try {
            $credentials = GoogleCredentials::get();

            return [
                'checkbox' => trim((string) ($credentials->recaptcha_site_key ?? '')),
                'score' => trim((string) ($credentials->recaptcha_score_site_key ?? '')),
            ];
        } catch (Throwable) {
            return ['checkbox' => '', 'score' => ''];
        }
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
            'remote_addr' => \Wonder\Http\ClientIp::resolve(),
        ];

        // Presenza credenziali: utile per diagnosticare misconfigurazioni
        // senza loggare i valori. Best-effort, non deve mai far fallire il log.
        try {
            $credentials = GoogleCredentials::get();
            $context['has_gcp_project_id'] = trim((string) ($credentials->gcp_project_id ?? '')) !== '';
            $context['has_gcp_api_key'] = trim((string) ($credentials->gcp_api_key ?? '')) !== '';
            $context['has_site_key'] = trim((string) ($credentials->recaptcha_site_key ?? '')) !== '';
            $context['has_score_site_key'] = trim((string) ($credentials->recaptcha_score_site_key ?? '')) !== '';
        } catch (Throwable) {
            // credenziali non disponibili nel contesto corrente: si ignora.
        }

        return $context;
    }
}
