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

require __DIR__ . '/../../vendor/autoload.php';

// Isola i log in una dir temporanea (Logger scrive in getcwd()/storage/logs).
$tmp = sys_get_temp_dir() . '/wi-recaptcha-test-' . getmypid();
@mkdir($tmp, 0777, true);
chdir($tmp);

/** Engine fake: ritorna un risultato configurabile o lancia. */
final class FakeEngine
{
    public int $calls = 0;
    public ?string $lastToken = null;
    public ?string $lastAction = null;

    public function __construct(
        private bool $valid = true,
        private bool $throws = false,
    ) {}

    public function verify($token, $action): object
    {
        $this->calls++;
        $this->lastToken = $token;
        $this->lastAction = $action;

        if ($this->throws) {
            throw new RuntimeException('engine boom');
        }

        return (object) ['valid' => $this->valid, 'result' => ['tokenProperties' => ['valid' => $this->valid ? 'true' : 'false']]];
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

$valid = ['g-recaptcha-token' => 'tok', 'g-recaptcha-action' => 'contact'];

check('1. token valido + action corretta → verify() non lancia', function () use ($valid) {
    $engine = new FakeEngine(valid: true);
    RecaptchaGuard::for('contact')->withRequest($valid)->withEngine($engine)->verify();
    assertTrue($engine->calls === 1, 'engine deve essere chiamato una volta');
    assertTrue($engine->lastAction === 'contact', 'action attesa propagata all\'engine');
});

check('2. token mancante → 617', function () {
    assert617(function () {
        RecaptchaGuard::for('contact')
            ->withRequest(['g-recaptcha-action' => 'contact'])
            ->withEngine(new FakeEngine(valid: true))
            ->verify();
    });
});

check('3. action mancante → 617', function () {
    assert617(function () {
        RecaptchaGuard::for('contact')
            ->withRequest(['g-recaptcha-token' => 'tok'])
            ->withEngine(new FakeEngine(valid: true))
            ->verify();
    });
});

check('4. action mismatch → 617 (engine non chiamato)', function () {
    $engine = new FakeEngine(valid: true);
    assert617(function () use ($engine) {
        RecaptchaGuard::for('contact')
            ->withRequest(['g-recaptcha-token' => 'tok', 'g-recaptcha-action' => 'submit'])
            ->withEngine($engine)
            ->verify();
    });
    assertTrue($engine->calls === 0, 'su mismatch l\'engine non deve essere chiamato');
});

check('5. engine lancia eccezione → 617', function () use ($valid) {
    assert617(function () use ($valid) {
        RecaptchaGuard::for('contact')
            ->withRequest($valid)
            ->withEngine(new FakeEngine(throws: true))
            ->verify();
    });
});

check('6. engine ritorna valid=false → 617', function () use ($valid) {
    assert617(function () use ($valid) {
        RecaptchaGuard::for('contact')
            ->withRequest($valid)
            ->withEngine(new FakeEngine(valid: false))
            ->verify();
    });
});

check('7. passes() ritorna bool senza propagare il 617', function () use ($valid) {
    $ok = RecaptchaGuard::for('contact')->withRequest($valid)->withEngine(new FakeEngine(valid: true))->passes();
    assertTrue($ok === true, 'passes() deve essere true su token valido');

    $ko = RecaptchaGuard::for('contact')->withRequest($valid)->withEngine(new FakeEngine(valid: false))->passes();
    assertTrue($ko === false, 'passes() deve essere false su token invalido');
});

check('8. action vuota in for() accetta qualunque action ricevuta (BC legacy)', function () use ($valid) {
    $engine = new FakeEngine(valid: true);
    RecaptchaGuard::for('')->withRequest($valid)->withEngine($engine)->verify();
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

echo "\n{$tests} test, {$failures} fallimenti\n";
exit($failures === 0 ? 0 : 1);
