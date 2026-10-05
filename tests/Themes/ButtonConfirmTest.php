<?php
/** php tests/Themes/ButtonConfirmTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Button;

foreach (['bootstrap', 'wonder'] as $theme) {
    echo "\n{$theme}\n";

    check('la firma di prima: confirm($testo) su post() va sul form', function () use ($theme) {
        $html = Button::post('/del', 'Elimina')->confirm('Eliminare "tutto"?')->render($theme);

        return str_contains($html, '<form method="post" action="/del" data-wi-confirm="Eliminare &quot;tutto&quot;?">')
            && !str_contains($html, 'onsubmit')
            && !str_contains($html, 'window.confirm')
            && !str_contains($html, 'data-wi-confirm-');
    });

    check('title, ok e variant come argomenti nominati', function () use ($theme) {
        $html = Button::post('/del', 'Elimina')
            ->confirm('Sicuro?', title: 'Elimina <ordine>', ok: 'Sì', variant: 'danger')
            ->render($theme);

        return str_contains($html, 'data-wi-confirm="Sicuro?" data-wi-confirm-title="Elimina &lt;ordine&gt;" data-wi-confirm-ok="Sì" data-wi-confirm-variant="danger">');
    });

    check('un link o un bottone non POST porta la conferma sul tag', function () use ($theme) {
        $link = Button::make('Esci', '/logout')->confirm('Uscire?')->render($theme);
        $button = Button::make('Svuota')->confirm('Svuotare?', ok: 'Svuota')->render($theme);

        return preg_match('#<a href="/logout" data-wi-confirm="Uscire\?" class="#', $link) === 1
            && preg_match('#<button data-wi-confirm="Svuotare\?" data-wi-confirm-ok="Svuota" class="#', $button) === 1;
    });

    check('confirm() chiamato due volte tiene solo l\'ultima', function () use ($theme) {
        $html = Button::post('/x', 'X')->confirm('Primo', title: 'T')->confirm('Secondo')->render($theme);

        return str_contains($html, 'data-wi-confirm="Secondo"') && !str_contains($html, 'data-wi-confirm-title');
    });

    check('confirm(\'\') toglie la conferma', function () use ($theme) {
        return !str_contains(Button::post('/x', 'X')->confirm('Sì?')->confirm('')->render($theme), 'data-wi-confirm');
    });
}

check('una variante non valida si ferma subito', function () {
    try {
        Button::post('/x', 'X')->confirm('Ok?', variant: 'danger" onclick="x');
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

summary();
