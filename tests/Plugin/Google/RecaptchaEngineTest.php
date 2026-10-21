<?php
/** php tests/Plugin/Google/RecaptchaEngineTest.php — il motore reCAPTCHA classifica la risposta di Google. */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Plugin\Google\Security\reCAPTCHA;

echo "reCAPTCHA engine\n";

/** Finta Call: risposta e codice HTTP fissati, ricorda l'evento spedito. */
final class FakeCall
{
    public static array $events = [];

    public function __construct(private array $reply, private int $code, array $event)
    {
        self::$events[] = $event;
    }

    public function contentType($type): static { return $this; }
    public function connectTimeout(int $seconds): static { return $this; }
    public function timeout(int $seconds): static { return $this; }
    public function call(): array { return $this->reply; }
    public function httpCode(): int { return $this->code; }
}

function engine(array $reply, int $code): reCAPTCHA
{
    $credentials = (object) ['gcp_project_id' => 'p', 'gcp_api_key' => 'k', 'recaptcha_site_key' => 'site-checkbox'];

    return new reCAPTCHA(fn (string $url, array $event) => new FakeCall($reply, $code, $event), $credentials);
}

function ok(array $json): array
{
    return ['success' => true, 'error' => null, 'errno' => 0, 'result' => json_encode($json)];
}

check('200 valido: valid, punteggio, azione, available', function () {
    $r = engine(ok(['tokenProperties' => ['valid' => true, 'action' => 'contact'], 'riskAnalysis' => ['score' => 0.9]]), 200)->verify('tok', 'contact');

    return $r->valid === true && $r->score === 0.9 && $r->action === 'contact' && $r->available === true && $r->httpCode === 200;
});

check('200 con token non valido: valid falso ma Google ha risposto', function () {
    $r = engine(ok(['tokenProperties' => ['valid' => false, 'invalidReason' => 'MALFORMED']]), 200)->verify('tok', 'contact');

    return $r->valid === false && $r->available === true && $r->score === null && $r->action === null;
});

check('400: Google ha risposto, non valido', function () {
    $r = engine(ok(['error' => ['code' => 400]]), 400)->verify('tok', 'contact');

    return $r->valid === false && $r->available === true;
});

check('429 e 503: non disponibile', function () {
    $a = engine(ok(['error' => ['code' => 429]]), 429)->verify('tok', 'contact');
    $b = engine(['success' => true, 'error' => null, 'errno' => 0, 'result' => 'Service Unavailable'], 503)->verify('tok', 'contact');

    return $a->available === false && $a->valid === false && $b->available === false;
});

check('401 e 403: Google ha risposto, errore di configurazione (C-PWD-I3)', function () {
    $a = engine(ok(['error' => ['code' => 401]]), 401)->verify('tok', 'contact');
    $b = engine(ok(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED']]), 403)->verify('tok', 'contact');
    $c = engine(ok(['error' => ['code' => 429]]), 429)->verify('tok', 'contact');

    return $a->available === true && $a->valid === false && $a->config === true
        && $b->available === true && $b->config === true && $c->config === false;
});

check('errore di rete (success falso, codice 0): non disponibile', function () {
    $r = engine(['success' => false, 'error' => 'timeout', 'errno' => 28, 'result' => null], 0)->verify('tok', 'contact');

    return $r->available === false && $r->valid === false && $r->httpCode === 0;
});

check('200 senza JSON: non disponibile', function () {
    $r = engine(['success' => true, 'error' => null, 'errno' => 0, 'result' => '<html>'], 200)->verify('tok', 'contact');

    return $r->available === false;
});

check('valid come stringa "true" (forma storica) resta valido', function () {
    return reCAPTCHA::classify(['tokenProperties' => ['valid' => 'true']], 200)->valid === true;
});

check('azione vuota di Google → null', function () {
    return reCAPTCHA::classify(['tokenProperties' => ['valid' => true, 'action' => ' ']], 200)->action === null;
});

check('la chiave passata va nell\'evento; senza, la chiave a casella', function () {
    FakeCall::$events = [];
    engine(ok(['tokenProperties' => ['valid' => true]]), 200)->verify('tok', 'contact', 'site-score');
    engine(ok(['tokenProperties' => ['valid' => true]]), 200)->verify('tok', 'submit');

    return FakeCall::$events[0]['event']['siteKey'] === 'site-score'
        && FakeCall::$events[0]['event']['expectedAction'] === 'contact'
        && FakeCall::$events[1]['event']['siteKey'] === 'site-checkbox';
});

summary();
