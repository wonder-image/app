<?php
/** php tests/Themes/StepsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Steps;

$percorso = static fn (): Steps => Steps::make('Passi <checkout>')
    ->step('Carrello', '/cart/', 'done')
    ->step('Spedizione & ritiro', '/checkout/', 'current')
    ->step('Pagamento', '/checkout/payment/');

foreach (['wonder', 'bootstrap'] as $theme) {
    check("{$theme}: nav con aria-label escapata e un elenco ordinato", function () use ($percorso, $theme) {
        $html = $percorso()->render($theme);

        return str_starts_with($html, '<nav aria-label="Passi &lt;checkout&gt;"><ol ')
            && str_ends_with($html, '</ol></nav>')
            && substr_count($html, '<li ') === 3;
    });

    check("{$theme}: link solo sul passo fatto", function () use ($percorso, $theme) {
        $html = $percorso()->render($theme);

        return substr_count($html, '<a ') === 1
            && str_contains($html, '<a href="/cart/">Carrello</a>')
            && !str_contains($html, 'href="/checkout/"')
            && !str_contains($html, 'href="/checkout/payment/"');
    });

    check("{$theme}: aria-current solo sul passo in corso, testo escapato", function () use ($percorso, $theme) {
        $html = $percorso()->render($theme);

        return substr_count($html, 'aria-current="step"') === 1
            && preg_match('/aria-current="step">Spedizione &amp; ritiro<\/li>/', $html) === 1;
    });

    check("{$theme}: il passo da fare è spento e non cliccabile", fn () =>
        preg_match('/aria-disabled="true">Pagamento<\/li>/', $percorso()->render($theme)) === 1
    );

    check("{$theme}: stato sconosciuto vale todo, done senza href è solo testo", function () use ($theme) {
        $html = Steps::make()->step('Uno', null, 'done')->step('Due', '/due/', 'active')->render($theme);

        return !str_contains($html, '<a ')
            && !str_contains($html, 'aria-label')
            && preg_match('/aria-disabled="true">Due<\/li>/', $html) === 1
            && str_contains($html, '>Uno</li>');
    });

    check("{$theme}: classi e attributi dati vanno sull'ol", function () use ($percorso, $theme) {
        $html = $percorso()->class('mb-4')->attr('data-x', '1')->render($theme);
        $base = $theme === 'wonder' ? 'wi-steps' : 'breadcrumb mb-0';

        return str_contains($html, '<ol class="'.$base.' mb-4" data-x="1">');
    });

    check("{$theme}: id() arriva sull'ol", fn () =>
        str_contains(Steps::make()->step('Uno')->id('passi')->render($theme), 'id="passi"')
    );
}

check('wonder: le classi della lib', function () use ($percorso) {
    $html = $percorso()->render('wonder');

    return str_contains($html, '<li class="wi-steps__item is-done"><a href="/cart/">')
        && str_contains($html, '<li class="wi-steps__item" aria-current="step">')
        && str_contains($html, '<li class="wi-steps__item is-disabled" aria-disabled="true">');
});

check('bootstrap: il breadcrumb', function () use ($percorso) {
    $html = $percorso()->render('bootstrap');

    return str_contains($html, '<li class="breadcrumb-item"><a href="/cart/">')
        && str_contains($html, '<li class="breadcrumb-item active" aria-current="step">')
        && str_contains($html, '<li class="breadcrumb-item text-body-tertiary" aria-disabled="true">');
});

check('la guida dei componenti e il CHANGELOG raccontano Choice, ChoiceGroup e Steps', function () {
    $root = dirname(__DIR__, 2);
    $docs = (string) file_get_contents($root.'/docs/app/concetti/componenti/README.md');
    $changelog = (string) file_get_contents($root.'/CHANGELOG.md');

    return str_contains($docs, '| `Choice` | `Elements/Components/Choice.php`')
        && str_contains($docs, '| `ChoiceGroup` | `Elements/Components/ChoiceGroup.php`')
        && str_contains($docs, '| `Steps` | `Elements/Components/Steps.php`')
        && str_contains($docs, '## Choice, ChoiceGroup e Steps')
        && str_contains($docs, 'data-choice-list')
        && str_contains($changelog, '`Choice`')
        && str_contains($changelog, '`Steps`');
});

summary();
