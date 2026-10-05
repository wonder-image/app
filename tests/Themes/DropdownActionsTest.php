<?php
/** php tests/Themes/DropdownActionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Dropdown;

// Il token CSRF esce solo con una sessione attiva.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_start();
$_SESSION = [];

$menu = static fn (): Dropdown => Dropdown::make('Azioni')
    ->item('Modifica', '/edit/1')
    ->action('Copia link', ['data-copy' => '/p/1'])
    ->divider()
    ->text('Ultima modifica ieri')
    ->item('Elimina', '/delete/1', [
        'method' => 'post',
        'variant' => 'danger',
        'confirm' => 'Eliminare <la> voce?',
        'confirm_title' => 'Elimina',
        'confirm_ok' => 'Sì, elimina',
    ]);

$singleClass = static fn (string $html): bool => preg_match('/<[a-z]+\b[^>]*\bclass="[^"]*"[^>]*\bclass="/', $html) === 0;

foreach (['bootstrap' => ['dropdown-item', 'text-danger'], 'wonder' => ['wi-dropdown-item', 'tx-danger']] as $theme => [$itemClass, $danger]) {
    echo "\n{$theme}\n";

    check('la voce POST è un form con token, action e un submit', function () use ($menu, $theme, $itemClass, $danger) {
        $html = $menu()->render($theme);

        return preg_match('#<form method="post" action="/delete/1" data-wi-confirm="Eliminare &lt;la&gt; voce\?" data-wi-confirm-title="Elimina" data-wi-confirm-ok="Sì, elimina"><input type="hidden" name="_csrf"[^>]*><button type="submit" class="'.$itemClass.' '.$danger.'">Elimina</button></form>#u', $html) === 1
            && substr_count($html, 'name="_csrf"') === 1;
    });

    check('niente window.confirm né onsubmit', function () use ($menu, $theme) {
        $html = $menu()->render($theme);

        return !str_contains($html, 'window.confirm') && !str_contains($html, 'onsubmit');
    });

    check('action() è un <button type="button"> senza href, con i suoi attributi', function () use ($menu, $theme, $itemClass) {
        return str_contains($menu()->render($theme), '<button type="button" class="'.$itemClass.'" data-copy="/p/1">Copia link</button>');
    });

    check('la conferma su una voce non POST va sul tag', function () use ($theme) {
        $html = Dropdown::make('X')
            ->item('Esci', '/logout', ['confirm' => 'Uscire?'])
            ->action('Svuota', [], ['confirm' => 'Svuotare?', 'confirm_variant' => 'warning'])
            ->render($theme);

        return preg_match('#<a href="/logout" class="[^"]*" data-wi-confirm="Uscire\?">#', $html) === 1
            && preg_match('#<button type="button" class="[^"]*" data-wi-confirm="Svuotare\?" data-wi-confirm-variant="warning">#', $html) === 1
            && !str_contains($html, '<form');
    });

    check('toggleClass, menuClass, itemClass si aggiungono alle classi del tema', function () use ($menu, $theme, $itemClass, $singleClass) {
        $html = $menu()->toggleClass('px-4')->menuClass('shadow')->itemClass('small')->render($theme);

        return preg_match('/<button type="button" class="btn [^"]*px-4"/', $html) === 1
            && preg_match('/<(ul|div) class="[^"]*(dropdown-menu|wi-dropdown-list)[^"]* shadow"/', $html) === 1
            && str_contains($html, 'class="'.$itemClass.' small" data-copy')
            && str_contains($html, 'class="'.$itemClass.' small">Modifica')
            && preg_match('/type="submit" class="'.$itemClass.' [a-z-]+ small"/', $html) === 1
            && $singleClass($html);
    });

    check('class negli attributes della voce entra nello stesso attributo', function () use ($theme, $itemClass, $singleClass) {
        $html = Dropdown::make('X')
            ->item('Link', '/l', ['title' => 'T', 'attributes' => ['class' => 'mine', 'title' => 'doppio', 'data-a' => '1']])
            ->action('Btn', ['class' => 'b', 'type' => 'submit'])
            ->render($theme);

        return str_contains($html, '<a href="/l" class="'.$itemClass.' mine" title="T" data-a="1">')
            && str_contains($html, '<button type="button" class="'.$itemClass.' b">')
            && $singleClass($html);
    });

    check('una voce POST disabilitata disabilita il submit', function () use ($theme) {
        $html = Dropdown::make('X')->item('No', '/x', ['method' => 'post', 'disabled' => true])->render($theme);

        return preg_match('/<button type="submit" class="[^"]*disabled" disabled>No</', $html) === 1;
    });
}

echo "\nconfigurazione\n";

check('text() resta la voce di solo testo', function () use ($menu) {
    return str_contains($menu()->render('bootstrap'), '<li><span class="dropdown-item-text">Ultima modifica ieri</span></li>');
});

check('Wonder: separatore wi-dropdown-divider, testo e intestazione non cliccabili', function () use ($menu) {
    $html = $menu()->header('Gruppo')->render('wonder');

    return str_contains($html, '<div class="wi-dropdown-divider" role="separator"></div>')
        && !str_contains($html, '"dropdown-divider"')
        && str_contains($html, '<div class="wi-dropdown-item wi-dropdown-text">Ultima modifica ieri</div>')
        && str_contains($html, '<div class="wi-dropdown-item wi-dropdown-text fw-700">Gruppo</div>');
});

check('nessuna classe chiude con uno spazio', function () use ($menu) {
    foreach (['bootstrap', 'wonder'] as $theme) {
        $html = $menu()->render($theme).\Wonder\Elements\Components\Button::make('A', '/a')->render($theme);
        if (preg_match('/class="[^"]* "/', $html) === 1) {
            return false;
        }
    }

    return true;
});

check('method get lascia la voce un link', function () {
    $items = Dropdown::make('X')->item('Vai', '/go', ['method' => 'get'])->getItems();

    return $items[0]['kind'] === 'link' && !isset($items[0]['method']);
});

check('items() normalizza come item()', function () {
    $items = Dropdown::make('X')->items([['label' => 'P', 'href' => '/p', 'method' => 'post', 'confirm' => 'Ok?']])->getItems();

    return $items[0]['kind'] === 'post' && $items[0]['confirm'] === 'Ok?';
});

check('metodo, href e varianti non validi si fermano subito', function () {
    $fails = 0;
    foreach ([
        static fn () => Dropdown::make('X')->item('A', '/a', ['method' => 'delete']),
        static fn () => Dropdown::make('X')->item('A', '', ['method' => 'post']),
        static fn () => Dropdown::make('X')->item('A', '/a', ['variant' => 'x" onclick="y']),
        static fn () => Dropdown::make('X')->item('A', '/a', ['confirm' => 'Ok?', 'confirm_variant' => 'bad variant']),
    ] as $build) {
        try {
            $build();
        } catch (\InvalidArgumentException $e) {
            $fails++;
        }
    }

    return $fails === 4;
});

summary();
