<?php
/** php tests/Security/RecaptchaConfigTest.php — chiave v3 e badge nella configurazione. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

echo "RecaptchaConfig\n";

$root = dirname(__DIR__, 2);
$read = fn (string $path): string => (string) file_get_contents($root.'/'.$path);

check('Credentials dell\'app: env e colonne per chiave v3 e badge, badge visibile di base', function () use ($read) {
    $source = $read('class/App/Credentials.php');

    return str_contains($source, "self::envOrRow('G_RECAPTCHA_SCORE_SITE_KEY'")
        && str_contains($source, "'g_recaptcha_score_site_key'")
        && str_contains($source, "self::envOrRow('G_RECAPTCHA_BADGE'")
        && str_contains($source, "'g_recaptcha_badge' => 'true'");
});

check('Credentials di Google: recaptcha_score_site_key e recaptcha_badge', function () use ($read) {
    $source = $read('class/Plugin/Google/Credentials.php');

    return str_contains($source, '->recaptcha_score_site_key = $default->g_recaptcha_score_site_key')
        && str_contains($source, '->recaptcha_badge = $default->g_recaptcha_badge');
});

check('modello: colonne e campi, badge \'true\' di base', function () use ($read) {
    $source = $read('class/App/Models/Config/Security.php');

    return str_contains($source, "Column::key('g_recaptcha_score_site_key')")
        && str_contains($source, "Column::key('g_recaptcha_badge')->default('true')")
        && str_contains($source, "Field::key('g_recaptcha_score_site_key')")
        && str_contains($source, "Field::key('g_recaptcha_badge')");
});

check('backend: etichette, select del badge e campi nel riquadro reCAPTCHA', function () use ($read) {
    $source = $read('class/App/Resources/Config/SecurityResource.php');

    return str_contains($source, "'Chiave Sito v2 (casella)'")
        && str_contains($source, "'Chiave Sito v3 (punteggio)'")
        && str_contains($source, "'Badge v3'")
        && str_contains($source, "FormField::key('g_recaptcha_badge')->select(['true' => 'Visibile', 'false' => 'Nascosto'])->value('true')->required()")
        && str_contains($source, "static::getInput('g_recaptcha_score_site_key')")
        && str_contains($source, "static::getInput('g_recaptcha_badge')");
});

check('dev-shared: la riga della chiave v3', fn () => str_contains($read('docs/app/piattaforma/dev-shared.md'), '`G_RECAPTCHA_SCORE_SITE_KEY`'));

summary();
