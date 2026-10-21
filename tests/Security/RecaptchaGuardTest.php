<?php

/**
 * Test standalone per Wonder\App\Security\RecaptchaGuard.
 *
 * Il repo non ha phpunit/pest: questo file è un runner minimale eseguibile con
 *
 *   php tests/Security/RecaptchaGuardTest.php
 *
 * L'engine reCAPTCHA è iniettato (fake) via withEngine(), quindi nessuna HTTP
 * reale. I log finiscono in una cartella temporanea (chdir) e vengono ignorati.
 */

declare(strict_types=1);

use Wonder\App\Security\RecaptchaGuard;
use Wonder\App\Security\RecaptchaChallenge;

require __DIR__ . '/../../vendor/autoload.php';

// Ambiente fissato a produzione: in locale la guardia salta sempre (C-PWD-I3).
unset($_SERVER['APP_ENV']);
putenv('APP_ENV');
$_ENV['APP_ENV'] = 'production';
\Wonder\App\Environment::reset();

// Isola i log in una dir temporanea (Logger scrive in getcwd()/storage/logs).
$tmp = sys_get_temp_dir() . '/wi-recaptcha-test-' . getmypid();
@mkdir($tmp, 0777, true);
chdir($tmp);

// In CLI session_start() dopo un output fallisce: tutto l'output va nel buffer.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
session_save_path($tmp);
session_start();

/** Engine fake: risultato configurabile (valid, punteggio, azione, disponibilità) o eccezione. */
final class FakeEngine
{
    public int $calls = 0;
    public ?string $lastToken = null;
    public ?string $lastAction = null;
    public ?string $lastSiteKey = null;

    public function __construct(
        private bool $valid = true,
        private bool $throws = false,
        private ?float $score = null,
        private ?string $action = null,
        private bool $available = true,
        private int $httpCode = 200,
    ) {}

    public function verify(string $token, string $action, ?string $siteKey = null): object
    {
        $this->calls++;
        $this->lastToken = $token;
        $this->lastAction = $action;
        $this->lastSiteKey = $siteKey;

        if ($this->throws) {
            throw new RuntimeException('engine boom');
        }

        return (object) [
            'valid' => $this->valid,
            'score' => $this->score,
            'action' => $this->action,
            'available' => $this->available,
            'httpCode' => $this->httpCode,
            'result' => ['tokenProperties' => ['valid' => $this->valid], 'event' => ['token' => $token]],
        ];
    }
}

$tests = 0;
$failures = 0;

function check(string $name, callable $fn): void
{
    global $tests, $failures;
    $tests++;

    try {
        $fn();
        echo "  PASS  {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n        {$e->getMessage()}\n";
    }
}

function assertTrue(bool $cond, string $msg = 'assertTrue failed'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

/** Asserisce che $fn lanci una RuntimeException con codice 617. */
function assert617(callable $fn): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        assertTrue($e->getCode() === RecaptchaGuard::ERROR_CODE, "codice atteso 617, ottenuto {$e->getCode()}");
        return;
    }

    throw new RuntimeException('attesa RuntimeException(617), nessuna lanciata');
}

/** Asserisce che $fn lanci RecaptchaChallenge (618). */
function assert618(callable $fn): void
{
    try {
        $fn();
    } catch (RecaptchaChallenge $e) {
        assertTrue($e->getCode() === RecaptchaChallenge::CHALLENGE_CODE, "codice atteso 618, ottenuto {$e->getCode()}");
        return;
    }

    throw new RuntimeException('attesa RecaptchaChallenge(618), nessuna lanciata');
}

/** Guardia con la sola chiave a casella (il comportamento di prima). */
function guard(string $action = 'contact'): RecaptchaGuard
{
    return RecaptchaGuard::for($action)->withSiteKeys('site-checkbox', '');
}

$score = ['g-recaptcha-token' => 'tok', 'g-recaptcha-action' => 'contact', 'g-recaptcha-type' => 'score'];
$logFile = $tmp . '/storage/logs/error/recaptcha.log';

$valid = ['g-recaptcha-token' => 'tok', 'g-recaptcha-action' => 'contact'];

check('1. token valido + action corretta → verify() non lancia', function () use ($valid) {
    $engine = new FakeEngine(valid: true);
    guard()->withRequest($valid)->withEngine($engine)->verify();
    assertTrue($engine->calls === 1, 'engine deve essere chiamato una volta');
    assertTrue($engine->lastAction === 'contact', 'action attesa propagata all\'engine');
    assertTrue($engine->lastSiteKey === 'site-checkbox', 'senza tipo si usa la chiave a casella');
});

check('2. token mancante → 617', function () {
    assert617(function () {
        guard()
            ->withRequest(['g-recaptcha-action' => 'contact'])
            ->withEngine(new FakeEngine(valid: true))
            ->verify();
    });
});

check('3. action mancante → 617', function () {
    assert617(function () {
        guard()
            ->withRequest(['g-recaptcha-token' => 'tok'])
            ->withEngine(new FakeEngine(valid: true))
            ->verify();
    });
});

check('4. action mismatch → 617 (engine non chiamato)', function () {
    $engine = new FakeEngine(valid: true);
    assert617(function () use ($engine) {
        guard()
            ->withRequest(['g-recaptcha-token' => 'tok', 'g-recaptcha-action' => 'submit'])
            ->withEngine($engine)
            ->verify();
    });
    assertTrue($engine->calls === 0, 'su mismatch l\'engine non deve essere chiamato');
});

check('5. engine lancia eccezione → la verifica si salta', function () use ($valid) {
    $engine = new FakeEngine(throws: true);
    guard()->withRequest($valid)->withEngine($engine)->verify();
    assertTrue($engine->calls === 1, 'engine chiamato');
});

check('6. engine ritorna valid=false → 617', function () use ($valid) {
    assert617(function () use ($valid) {
        guard()
            ->withRequest($valid)
            ->withEngine(new FakeEngine(valid: false))
            ->verify();
    });
});

check('7. passes() ritorna bool senza propagare il 617', function () use ($valid) {
    $ok = guard()->withRequest($valid)->withEngine(new FakeEngine(valid: true))->passes();
    assertTrue($ok === true, 'passes() deve essere true su token valido');

    $ko = guard()->withRequest($valid)->withEngine(new FakeEngine(valid: false))->passes();
    assertTrue($ko === false, 'passes() deve essere false su token invalido');
});

check('8. action vuota in for() accetta qualunque action ricevuta (BC legacy)', function () use ($valid) {
    $engine = new FakeEngine(valid: true);
    guard('')->withRequest($valid)->withEngine($engine)->verify();
    assertTrue($engine->lastAction === 'contact', 'usa l\'action ricevuta quando attesa è vuota');
});

// --- Derivazione action da formSchema() (Resource::recaptchaAction) ---

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormField;

final class ContactFormResource extends Resource
{
    public static function formSchema(): array
    {
        return [
            FormField::key('email')->email()->required(),
            FormField::key('recaptcha')->recaptcha('contact'),
        ];
    }
}

final class DefaultActionResource extends Resource
{
    public static function formSchema(): array
    {
        return [FormField::key('recaptcha')->recaptcha()];
    }
}

final class NoRecaptchaResource extends Resource
{
    public static function formSchema(): array
    {
        return [FormField::key('email')->email()];
    }
}

/** Invoca recaptchaAction() sul $resourceClass concreto (late static binding). */
function actionOf(string $resourceClass): string
{
    $method = new ReflectionMethod($resourceClass, 'recaptchaAction');

    return (string) $method->invoke(null);
}

check('9. action derivata da FormField::recaptcha(\'contact\')', function () {
    assertTrue(actionOf(ContactFormResource::class) === 'contact', 'attesa action contact');
});

check('10. recaptcha() senza action → default frontend submit', function () {
    assertTrue(actionOf(DefaultActionResource::class) === 'submit', 'attesa action submit');
});

check('11. nessun campo recaptcha → eccezione di configurazione (non 617)', function () {
    try {
        actionOf(NoRecaptchaResource::class);
    } catch (RuntimeException $e) {
        assertTrue($e->getCode() !== RecaptchaGuard::ERROR_CODE, 'non deve essere un 617 silenzioso');
        return;
    }

    throw new RuntimeException('attesa RuntimeException di configurazione');
});

// --- v3, sfida 618, fail-open ---

check('12. tipo score → chiave v3; punteggio alto passa', function () use ($score) {
    $engine = new FakeEngine(score: 0.9, action: 'contact');
    RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($score)->withEngine($engine)->verify();
    assertTrue($engine->lastSiteKey === 'site-score', 'attesa la chiave v3');
});

check('13. punteggio basso con le due chiavi → 618 e segno in sessione (letto una volta)', function () use ($score) {
    assert618(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($score)->withEngine(new FakeEngine(score: 0.1))->verify());
    assertTrue(RecaptchaGuard::challenged('contact') === true, 'segno atteso');
    assertTrue(RecaptchaGuard::challenged('contact') === false, 'il segno si consuma alla lettura');
});

check('14. punteggio basso con la sola chiave v3 → 617, nessun segno', function () use ($score) {
    assert617(fn () => RecaptchaGuard::for('contact')->withSiteKeys('', 'site-score')->withRequest($score)->withEngine(new FakeEngine(score: 0.1))->verify());
    assertTrue(RecaptchaGuard::challenged('contact') === false, 'nessun segno senza chiave a casella');
});

check('15. punteggio assente con tipo score → come basso (618)', function () use ($score) {
    assert618(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($score)->withEngine(new FakeEngine(score: null))->verify());
    RecaptchaGuard::challenged('contact');
});

check('16. tipo checkbox con la sola chiave v3 → 617, engine non chiamato', function () use ($valid) {
    $engine = new FakeEngine();
    assert617(fn () => RecaptchaGuard::for('contact')->withSiteKeys('', 'site-score')->withRequest($valid + ['g-recaptcha-type' => 'checkbox'])->withEngine($engine)->verify());
    assertTrue($engine->calls === 0, 'engine non chiamato');
});

check('17. tipo sconosciuto → 617, engine non chiamato', function () use ($valid) {
    $engine = new FakeEngine();
    assert617(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($valid + ['g-recaptcha-type' => 'foo'])->withEngine($engine)->verify());
    assertTrue($engine->calls === 0, 'engine non chiamato');
});

check('18. Google non disponibile (429, 500, 0, 404) → la verifica si salta', function () use ($valid) {
    foreach ([429, 500, 0, 404] as $code) {
        guard()->withRequest($valid)->withEngine(new FakeEngine(valid: false, available: false, httpCode: $code))->verify();
    }
});

check('19. azione di Google diversa → 617; vuota → passa', function () use ($valid) {
    assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(action: 'login'))->verify());
    guard()->withRequest($valid)->withEngine(new FakeEngine(action: null))->verify();
});

check('20. HTTP 400 (Google ha risposto, non valido) → 617', function () use ($valid) {
    assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(valid: false, available: true, httpCode: 400))->verify());
});

check('21. token assente → 617 anche se l\'engine lancerebbe (il fail-open viene dopo)', function () {
    assert617(fn () => guard()->withRequest(['g-recaptcha-action' => 'contact'])->withEngine(new FakeEngine(throws: true))->verify());
});

check('22. minScore(0.9) con 0.7 → 618', function () use ($score) {
    assert618(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->minScore(0.9)->withRequest($score)->withEngine(new FakeEngine(score: 0.7))->verify());
    RecaptchaGuard::challenged('contact');
});

check('23. challenged() senza sessione → false', function () {
    session_write_close();
    $result = RecaptchaGuard::challenged('contact');
    session_start();
    assertTrue($result === false, 'atteso false');
});

check('24. il successo cancella il segno della sfida', function () use ($score, $valid) {
    assert618(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($score)->withEngine(new FakeEngine(score: 0.1))->verify());
    RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($valid + ['g-recaptcha-type' => 'checkbox'])->withEngine(new FakeEngine())->verify();
    assertTrue(RecaptchaGuard::challenged('contact') === false, 'segno cancellato dal successo');
});

check('25. isFailure: 617 e 618 sì, altri no', function () {
    assertTrue(RecaptchaGuard::isFailure(new RuntimeException('x', 617)), '617');
    assertTrue(RecaptchaGuard::isFailure(new RecaptchaChallenge()), '618');
    assertTrue(!RecaptchaGuard::isFailure(new RuntimeException('x', 500)), '500');
    assertTrue(!RecaptchaGuard::isFailure(new LogicException('x', 617)), 'non RuntimeException');
});

check('26. passes() → false anche sul 618', function () use ($score) {
    $ok = RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest($score)->withEngine(new FakeEngine(score: 0.1))->passes();
    RecaptchaGuard::challenged('contact');
    assertTrue($ok === false, 'atteso false');
});

check('27. il log non contiene il token', function () use ($logFile) {
    @unlink($logFile);
    $request = ['g-recaptcha-token' => 'tok-segreto-123', 'g-recaptcha-action' => 'contact'];
    assert617(fn () => guard()->withRequest($request)->withEngine(new FakeEngine(valid: false))->verify());
    $log = (string) @file_get_contents($logFile);
    assertTrue($log !== '' && str_contains($log, 'invalid'), 'riga di log attesa');
    assertTrue(!str_contains($log, 'tok-segreto-123'), 'il token non deve finire nel log');
});

check('28. salto loggato: engine_unavailable, ERROR per 404, WARNING per 429', function () use ($valid, $logFile) {
    @unlink($logFile);
    guard()->withRequest($valid)->withEngine(new FakeEngine(available: false, httpCode: 404))->verify();
    $log = (string) @file_get_contents($logFile);
    assertTrue(str_contains($log, 'engine_unavailable') && str_contains($log, 'ERROR'), '404 → ERROR');

    @unlink($logFile);
    guard()->withRequest($valid)->withEngine(new FakeEngine(available: false, httpCode: 429))->verify();
    $log = (string) @file_get_contents($logFile);
    assertTrue(str_contains($log, 'engine_unavailable') && str_contains($log, 'WARNING') && !str_contains($log, 'ERROR'), '429 → WARNING');
});

check('29. configured() non lancia senza credenziali', function () {
    assertTrue(is_bool(RecaptchaGuard::configured()), 'atteso un bool');
});

check('30. 618 nelle notifiche delle cinque lingue', function () {
    foreach (['it', 'en', 'de', 'es', 'fr'] as $lang) {
        $data = json_decode((string) file_get_contents(__DIR__ . "/../../resources/lang/{$lang}/notifications.json"), true);
        assertTrue(trim((string) ($data['618']['text'] ?? '')) !== '', "618 mancante in {$lang}");
    }
    $it = json_decode((string) file_get_contents(__DIR__ . '/../../resources/lang/it/notifications.json'), true);
    assertTrue($it['618']['text'] === 'Conferma di non essere un robot', 'testo italiano della spec R4');
});

check('31. token malformato (100 KB o UTF-8 non valido) → 617, engine non chiamato', function () use ($valid, $score) {
    foreach (['100 KB' => str_repeat('a', 100 * 1024), 'UTF-8 non valido' => "tok\xC3\x28", 'spazio interno' => 'tok en'] as $label => $token) {
        foreach (['checkbox' => $valid, 'score' => $score] as $type => $request) {
            $engine = new FakeEngine(valid: true, score: 0.9, action: 'contact');
            assert617(fn () => RecaptchaGuard::for('contact')->withSiteKeys('site-checkbox', 'site-score')->withRequest(['g-recaptcha-token' => $token] + $request)->withEngine($engine)->verify());
            assertTrue($engine->calls === 0, "{$label}/{$type}: engine non chiamato");
        }
    }
});

check('32. token di 8192 caratteri stampabili → arriva all\'engine', function () use ($valid) {
    $engine = new FakeEngine(valid: true);
    guard()->withRequest(['g-recaptcha-token' => str_repeat('A-_9', 2048)] + $valid)->withEngine($engine)->verify();
    assertTrue($engine->calls === 1, 'engine chiamato');
});

// C-PWD-I3: fail-closed in produzione, 401/403 errore di configurazione, salto in locale.
check('33. failClosed: l\'engine lancia → 617 con log ERROR engine_error', function () use ($valid, $logFile) {
    @unlink($logFile);
    $engine = new FakeEngine(throws: true);
    assert617(fn () => guard()->withRequest($valid)->withEngine($engine)->failClosed(true)->verify());
    $log = (string) @file_get_contents($logFile);
    assertTrue($engine->calls === 1, 'engine chiamato');
    assertTrue(str_contains($log, 'engine_error') && str_contains($log, 'ERROR') && str_contains($log, 'fail_closed'), 'log ERROR engine_error');
});

check('34. failClosed: Google non disponibile (429, 500, 0) → 617', function () use ($valid) {
    foreach ([429, 500, 0] as $code) {
        assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(valid: false, available: false, httpCode: $code))->failClosed(true)->verify());
    }
});

check('35. failClosed() senza argomento in produzione → 617; failClosed(false) → salto', function () use ($valid) {
    assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(available: false, httpCode: 503))->failClosed()->verify());
    guard()->withRequest($valid)->withEngine(new FakeEngine(available: false, httpCode: 503))->failClosed(false)->verify();
});

check('36. HTTP 401/403 → 617 (configurazione, log ERROR) anche senza failClosed', function () use ($valid, $logFile) {
    foreach ([401, 403] as $code) {
        @unlink($logFile);
        assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(valid: false, available: false, httpCode: $code))->verify());
        $log = (string) @file_get_contents($logFile);
        assertTrue(str_contains($log, '"reason":"config"') && str_contains($log, 'ERROR'), "{$code}: log config ERROR");
    }
});

check('37. APP_ENV=local → salto con log, engine non chiamato, anche senza token e con failClosed', function () use ($logFile) {
    $_ENV['APP_ENV'] = 'local';
    \Wonder\App\Environment::reset();
    @unlink($logFile);
    $engine = new FakeEngine(valid: false);
    try {
        guard()->withRequest([])->withEngine($engine)->failClosed(true)->verify();
    } finally {
        $_ENV['APP_ENV'] = 'production';
        \Wonder\App\Environment::reset();
    }
    $log = (string) @file_get_contents($logFile);
    assertTrue($engine->calls === 0, 'engine non chiamato');
    assertTrue(str_contains($log, 'local_env') && str_contains($log, 'skipped'), 'salto loggato');
});

check('38. dietro proxy fidato il log registra l\'IP del client (ClientIp)', function () use ($valid, $logFile) {
    $saved = [$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null, $_ENV['APP_TRUST_PROXY'] ?? null];
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 203.0.113.7';
    $_ENV['APP_TRUST_PROXY'] = 'true';
    @unlink($logFile);
    try {
        assert617(fn () => guard()->withRequest(['g-recaptcha-action' => 'contact'])->verify());
    } finally {
        foreach (['REMOTE_ADDR' => 0, 'HTTP_X_FORWARDED_FOR' => 1] as $k => $i) {
            if ($saved[$i] === null) { unset($_SERVER[$k]); } else { $_SERVER[$k] = $saved[$i]; }
        }
        if ($saved[2] === null) { unset($_ENV['APP_TRUST_PROXY']); } else { $_ENV['APP_TRUST_PROXY'] = $saved[2]; }
    }
    $log = (string) @file_get_contents($logFile);
    assertTrue(str_contains($log, '"remote_addr":"203.0.113.7"'), 'remote_addr = ultima voce di X-Forwarded-For');
});

check('39. APP_ENV assente o non valido → produzione: niente salto, failClosed() chiude', function () use ($valid, $logFile) {
    foreach ([null, 'staging'] as $value) {
        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        putenv('APP_ENV');
        if ($value !== null) {
            $_ENV['APP_ENV'] = $value;
        }
        \Wonder\App\Environment::reset();
        try {
            @unlink($logFile);
            $engine = new FakeEngine(valid: false);
            assert617(fn () => guard()->withRequest([])->withEngine($engine)->verify());
            assertTrue($engine->calls === 0 && !str_contains((string) @file_get_contents($logFile), 'local_env'), 'nessun salto locale');
            assert617(fn () => guard()->withRequest($valid)->withEngine(new FakeEngine(throws: true))->failClosed()->verify());
        } finally {
            $_ENV['APP_ENV'] = 'production';
            \Wonder\App\Environment::reset();
        }
    }
});

check('40. in produzione senza chiavi del sito → 617 invalid, engine non chiamato', function () use ($valid, $logFile) {
    @unlink($logFile);
    $engine = new FakeEngine(valid: true);
    assert617(fn () => RecaptchaGuard::for('contact')->withSiteKeys('', '')->withRequest($valid)->withEngine($engine)->failClosed()->verify());
    assertTrue($engine->calls === 0, 'engine non chiamato');
    assertTrue(str_contains((string) @file_get_contents($logFile), '"reason":"invalid"'), 'log invalid');
});

// Il fail-closed del login frontend è verificato sul comportamento in tests/Auth/AuthControllerTest.php.

echo "\n{$tests} test, {$failures} fallimenti\n";
ob_end_flush();
exit($failures === 0 ? 0 : 1);
