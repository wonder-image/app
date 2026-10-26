<?php
/** php tests/Themes/BadgeInlineTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Theme;
use Wonder\Elements\Components\Badge;

// HTML di riferimento catturato prima di inline(): senza inline() non deve cambiare di un byte.
$cases = [
    'base' => fn () => Badge::make('Bozza'),
    'variant' => fn () => Badge::make('Ok')->variant('success'),
    'outline e pill' => fn () => Badge::make('A<b>')->variant('primary')->outline()->pill(),
    'icona a inizio' => fn () => Badge::make('Verificato')->variant('success')->icon('bi bi-check2'),
    'href e icona a fine' => fn () => Badge::to('/o/?a=1&b=2', 'Ordini')->variant('primary')->icon('bi bi-box-arrow-up-right', 'end'),
    'classe extra e title' => fn () => Badge::make('deprecato')->variant('warning')->title('Usa "X"')->addClass('ms-1'),
    'columnSpan' => fn () => Badge::make('C')->columnSpan(6),
    'link blank' => fn () => Badge::to('/x', 'X')->blank(),
];

$golden = [
    'bootstrap' => [
        'base' => '<div class="col-span-1"><span class="badge text-bg-secondary">Bozza</span></div>',
        'variant' => '<div class="col-span-1"><span class="badge text-bg-success">Ok</span></div>',
        'outline e pill' => '<div class="col-span-1"><span class="badge border border-primary text-primary bg-transparent rounded-pill">A&lt;b&gt;</span></div>',
        'icona a inizio' => '<div class="col-span-1"><span class="badge text-bg-success d-inline-flex align-items-center gap-1"><i class="bi bi-check2"></i> Verificato</span></div>',
        'href e icona a fine' => '<div class="col-span-1"><a href="/o/?a=1&amp;b=2" class="badge text-bg-primary d-inline-flex align-items-center gap-1">Ordini <i class="bi bi-box-arrow-up-right"></i></a></div>',
        'classe extra e title' => '<div class="col-span-1"><span title="Usa &quot;X&quot;" class="badge text-bg-warning ms-1">deprecato</span></div>',
        'columnSpan' => '<div class="col-span-6"><span class="badge text-bg-secondary">C</span></div>',
        'link blank' => '<div class="col-span-1"><a href="/x" target="_blank" rel="noopener noreferrer" class="badge text-bg-secondary">X</a></div>',
    ],
    'wonder' => [
        'base' => '<span class="badge badge-secondary">Bozza</span>',
        'variant' => '<span class="badge badge-success">Ok</span>',
        'outline e pill' => '<span class="badge badge-primary-o">A&lt;b&gt;</span>',
        'icona a inizio' => '<span class="badge badge-success"><i class="bi bi-check2"></i> Verificato</span>',
        'href e icona a fine' => '<a href="/o/?a=1&amp;b=2" class="badge badge-primary">Ordini <i class="bi bi-box-arrow-up-right"></i></a>',
        'classe extra e title' => '<span title="Usa &quot;X&quot;" class="badge badge-warning ms-1">deprecato</span>',
        'columnSpan' => '<span class="badge badge-secondary">C</span>',
        'link blank' => '<a href="/x" target="_blank" rel="noopener noreferrer" class="badge badge-secondary">X</a>',
    ],
];

// Il <span>/<a> dentro il <div> della colonna: è ciò che inline() deve restituire.
$inner = static fn (string $html): string => preg_replace('#^<div class="[^"]*">(.*)</div>$#s', '$1', $html);

foreach (['bootstrap', 'wonder'] as $theme) {
    echo "\n{$theme}\n";

    foreach ($cases as $name => $make) {
        check("senza inline() l'HTML resta quello di prima: {$name}", fn () =>
            $make()->render($theme) === $golden[$theme][$name]);

        check("inline() toglie solo la colonna: {$name}", fn () =>
            $make()->inline()->render($theme) === $inner($golden[$theme][$name]));

        check("inline(false) equivale a nessun inline(): {$name}", fn () =>
            $make()->inline()->inline(false)->render($theme) === $golden[$theme][$name]);
    }

    check('inline() vince su columnSpan(), in qualunque ordine', function () use ($theme) {
        $a = Badge::make('C')->columnSpan(6)->inline()->render($theme);
        $b = Badge::make('C')->inline()->columnSpan(['default' => 12, 'md' => 6])->render($theme);

        return !str_contains($a, '<div') && !str_contains($a, 'col-span') && $a === $b;
    });

    check('in linea si concatena dentro una cella', function () use ($theme) {
        $cell = 'Ordine 12 '.Badge::make('Pagato')->variant('success')->inline()->render($theme);

        return preg_match('#^Ordine 12 <span class="badge [^"]+">Pagato</span>$#', $cell) === 1;
    });

    check('attributi data-* restano sul tag in linea', function () use ($theme) {
        $html = Badge::make('3')->variant('warning')->inline()
            ->attr('data-bs-toggle', 'tooltip')->attr('data-bs-title', 'Sotto "scorta"')->render($theme);

        return str_starts_with($html, '<span data-bs-toggle="tooltip" data-bs-title="Sotto &quot;scorta&quot;" class="badge ');
    });
}

check('inline vale per qualunque valore truthy, come Button e Dropdown', fn () =>
    Badge::make('A')->schema('inline', 1)->render('bootstrap') === '<span class="badge text-bg-secondary">A</span>');

// In CLI il tema attivo è wonder, che non avvolge mai: per (string) imposta bootstrap e poi lo ripristina.
check('(string) con tema attivo bootstrap rispetta inline()', function () {
    $previous = Theme::get();
    Theme::set('bootstrap');

    try {
        return (string) Badge::make('X') === '<div class="col-span-1"><span class="badge text-bg-secondary">X</span></div>'
            && (string) Badge::make('X')->inline() === '<span class="badge text-bg-secondary">X</span>';
    } finally {
        Theme::set($previous);
    }
});

summary();
