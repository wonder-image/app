<?php
/** php tests/Themes/RecaptchaComponentTest.php — il riquadro reCAPTCHA del form nei quattro modi. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Themes\Wonder\Form\Components\reCAPTCHA;

echo "reCAPTCHA component\n";

$schema = ['recaptcha_action' => 'contact'];
$required = fn (string $html): bool => str_contains($html, 'name="g-recaptcha-token" required') && str_contains($html, 'name="g-recaptcha-action" required');

check('solo casella: come oggi, campi obbligatori, tipo checkbox', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', '', true, false);

    return $required($html) && !str_contains($html, 'data-wi-score-key') && str_contains($html, 'name="g-recaptcha-type" value="checkbox"')
        && str_contains($html, 'data-wi-site-key="site-key"') && str_contains($html, 'data-wi-action="contact"') && !str_contains($html, 'wi-recaptcha-terms');
});

check('solo v3: chiave v3, campi non obbligatori, tipo score', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, '', 'score-key', true, false);

    return !$required($html) && str_contains($html, 'data-wi-score-key="score-key"') && str_contains($html, 'name="g-recaptcha-type" value="score"');
});

check('tutte e due, senza sfida: tipo score, nessun data-wi-challenge', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', true, false);

    return !$required($html) && str_contains($html, 'value="score"') && !str_contains($html, 'data-wi-challenge');
});

check('tutte e due, sfida aperta: casella obbligatoria, tipo checkbox', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', true, true);

    return $required($html) && str_contains($html, 'data-wi-challenge="1"') && str_contains($html, 'value="checkbox"');
});

check('sfida senza chiave a casella o senza v3: ignorata', function () use ($schema) {
    return !str_contains(reCAPTCHA::markup($schema, '', 'score-key', true, true), 'data-wi-challenge')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', '', true, true), 'data-wi-challenge');
});

check('nessuna chiave: come oggi', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, '', '', true, false);

    return $required($html) && str_contains($html, 'data-wi-site-key=""') && str_contains($html, 'value="checkbox"');
});

check('data-wi-badge sempre "true" o "false"', function () use ($schema) {
    return str_contains(reCAPTCHA::markup($schema, 'site-key', '', true, false), 'data-wi-badge="true"')
        && str_contains(reCAPTCHA::markup($schema, 'site-key', 'score-key', false, false), 'data-wi-badge="false"');
});

check('attribuzione solo con badge nascosto e v3, con i due link di Google', function () use ($schema) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', false, false);

    return str_contains($html, '<p class="wi-recaptcha-terms">')
        && str_contains($html, 'href="https://policies.google.com/privacy"')
        && str_contains($html, 'href="https://policies.google.com/terms"')
        && !str_contains($html, '{{privacy}}')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', '', false, false), 'wi-recaptcha-terms')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', 'score-key', true, false), 'wi-recaptcha-terms');
});

check('valori di base e escape degli attributi', function () {
    $html = reCAPTCHA::markup(['recaptcha_action' => 'a"b'], 'k"1', 's"2', true, false);
    $base = reCAPTCHA::markup([], 'site-key', '', true, false);

    return str_contains($html, 'data-wi-action="a&quot;b"') && str_contains($html, 'data-wi-site-key="k&quot;1"') && str_contains($html, 'data-wi-score-key="s&quot;2"')
        && str_contains($base, 'data-wi-theme="light"') && str_contains($base, 'data-wi-size="normal"') && str_contains($base, 'data-wi-action="submit"');
});

check('testi dell\'attribuzione nelle cinque lingue', function () {
    foreach (['it', 'en', 'de', 'es', 'fr'] as $lang) {
        $data = json_decode((string) file_get_contents(__DIR__ . "/../../resources/lang/{$lang}/components.json"), true);
        $texts = $data['forms']['recaptcha'] ?? [];

        if (!str_contains((string) ($texts['notice'] ?? ''), '{{privacy}}') || !str_contains((string) ($texts['notice'] ?? ''), '{{terms}}')
            || trim((string) ($texts['privacy'] ?? '')) === '' || trim((string) ($texts['terms'] ?? '')) === '') {
            echo "    {$lang}: forms.recaptcha incompleto\n";

            return false;
        }
    }

    return true;
});

summary();
