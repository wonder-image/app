<?php
/** php tests/Elements/Components/DataItemTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Theme;
use Wonder\Elements\Components\DataItem;

Theme::set('bootstrap');

check('etichetta e valore stanno uno sopra l\'altro, nella colonna chiesta', function () {
    $html = DataItem::make('Email', 'a@b.it')->columnSpan(['default' => 12, 'sm' => 4])->render('bootstrap');

    return str_contains($html, '<div class="small text-muted">Email</div>')
        && str_contains($html, '<div>a@b.it</div>')
        && str_contains($html, 'col-span-12 col-span-sm-4');
});

check('etichetta e valore si escapano', function () {
    $html = DataItem::make('<b>E</b>', '<i>x</i> & y')->render('bootstrap');

    return str_contains($html, '&lt;b&gt;E&lt;/b&gt;') && str_contains($html, '&lt;i&gt;x&lt;/i&gt; &amp; y')
        && !str_contains($html, '<i>x</i>');
});

check('con html() il valore resta markup', function () {
    $html = DataItem::make('Stato', '<span class="badge">Attiva</span>')->html()->render('bootstrap');

    return str_contains($html, '<span class="badge">Attiva</span>');
});

check('senza valore compare il segnaposto, anche se è solo spazi', function () {
    $vuoto = DataItem::make('Telefono')->render('bootstrap');
    $spazi = DataItem::make('Telefono', '   ')->render('bootstrap');
    $altro = DataItem::make('Telefono')->placeholder('n/d')->render('bootstrap');

    return str_contains($vuoto, '<span class="text-muted">—</span>')
        && str_contains($spazi, '<span class="text-muted">—</span>')
        && str_contains($altro, '<span class="text-muted">n/d</span>');
});

check('lo zero è un valore, non un vuoto', fn () =>
    str_contains(DataItem::make('Pezzi', 0)->render('bootstrap'), '<div>0</div>')
);

check('l\'azione sta accanto all\'etichetta, com\'è scritta', function () {
    $html = DataItem::make('Nota', 'x')->action(' <a href="#" class="ms-1">Modifica</a>')->render('bootstrap');

    return str_contains($html, '<div class="small text-muted">Nota <a href="#" class="ms-1">Modifica</a></div>');
});

summary();
