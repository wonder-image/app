<?php
/** php tests/Backend/Support/HomeNavigationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Support\{HomeNavigation, HomeStatus};
use Wonder\Elements\Components\{LinkListCard, StatusCard};

$navigation = [
    ['title' => 'Home', 'icon' => 'bi-house', 'folder' => '', 'file' => ''],
    ['title' => 'Richieste', 'icon' => 'bi-envelope', 'folder' => 'requests', 'file' => '', 'authority' => ['admin']],
    ['title' => 'Guida', 'icon' => '', 'folder' => '', 'file' => 'https://guida.example.it'],
    ['title' => 'Dev', 'icon' => 'bi-terminal', 'folder' => '', 'file' => '', 'subnavs' => [
        ['title' => 'Riepilogo', 'folder' => 'app/scheduler', 'file' => ''],
        ['title' => 'Automazioni', 'subnavs' => [
            ['title' => 'Pianificazioni', 'folder' => 'app/scheduler/schedules', 'file' => ''],
            ['title' => 'Segreto', 'folder' => 'app/secret', 'file' => '', 'authority' => ['developer']],
        ]],
    ]],
    ['title' => 'Nascosta', 'icon' => 'bi-eye', 'subnavs' => [
        ['title' => 'Solo dev', 'folder' => 'x', 'file' => '', 'authority' => ['developer']],
    ]],
];

check('salta Home, le sezioni non permesse e quelle coi sottomenu tutti nascosti', function () use ($navigation) {
    $titles = array_column(HomeNavigation::sections($navigation, ['admin'], 'https://s.test/backend/'), 'title');

    return $titles === ['Richieste', 'Guida', 'Dev'];
});

check('risolve URL relativi e assoluti, filtra ogni livello e tiene il gruppo', function () use ($navigation) {
    $sections = HomeNavigation::sections($navigation, ['admin'], 'https://s.test/backend/');

    return $sections[0]['href'] === 'https://s.test/backend/requests'
        && $sections[1]['href'] === 'https://guida.example.it'
        && $sections[1]['icon'] === 'bi-bug'
        && $sections[2]['href'] === null
        && $sections[2]['links'] === [
            ['title' => 'Riepilogo', 'href' => 'https://s.test/backend/app/scheduler', 'group' => ''],
            ['title' => 'Pianificazioni', 'href' => 'https://s.test/backend/app/scheduler/schedules', 'group' => 'Automazioni'],
        ];
});

check('LinkListCard raggruppa i link per intestazione nell\'ordine di arrivo', function () {
    $card = LinkListCard::make('Dev')->links([
        ['title' => 'A', 'href' => '/a', 'group' => 'Log'],
        ['title' => 'B', 'href' => '/b', 'group' => ''],
        ['title' => 'C', 'href' => '/c', 'group' => 'Log'],
    ]);
    $groups = $card->groups();
    $flat = LinkListCard::make('Altro')->link('X', '/x');

    return array_column($groups, 'title') === ['Log', '']
        && array_column($groups[0]['links'], 'title') === ['A', 'C']
        && $card->isGrouped() && !$flat->isGrouped()
        && str_contains($card->render('bootstrap'), 'overflow-hidden');
});

check('lo scheduler è attivo, fermo o mai avviato in base all\'heartbeat', function () {
    $now = strtotime('2026-10-16 12:00:00 UTC');
    $active = HomeStatus::scheduler('2026-10-16 11:59:00', 0, $now, '/s/')->getSchema();
    $failing = HomeStatus::scheduler('2026-10-16 11:59:00', 2, $now, '/s/')->getSchema();
    $stopped = HomeStatus::scheduler('2026-10-16 11:00:00', 0, $now, '/s/')->getSchema();
    $never = HomeStatus::scheduler(null, 0, $now, '/s/')->getSchema();

    return $active['value'] === 'Attivo' && $active['variant'] === 'success'
        && $failing['variant'] === 'warning' && str_contains($failing['detail'], '2 esecuzioni fallite')
        && $stopped['value'] === 'Fermo' && $stopped['variant'] === 'danger'
        && $never['value'] === 'Mai avviato' && $never['variant'] === 'warning';
});

check('gli errori aperti sono in rosso, zero in verde', function () {
    return HomeStatus::errors(3, '/e/')->getSchema('variant') === 'danger'
        && HomeStatus::errors(0, '/e/')->getSchema('variant') === 'success';
});

check('senza i permessi delle Resource nessuna voce di stato, prima di leggere i dati', function () {
    return HomeStatus::items([], '/backend', []) === []
        && HomeStatus::items(['editor'], '/backend', []) === [];
});

final class HomeStatusTestItem implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        return StatusCard::make('Ordini', 4)->href($backendUrl.'/orders/');
    }

    public function order(): int
    {
        return 5;
    }
}

final class HomeStatusFailingItem implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        throw new RuntimeException('fonte giù');
    }

    public function order(): int
    {
        return 1;
    }
}

check('i moduli aggiungono, sostituiscono e tolgono voci per chiave', function () {
    $configs = [
        'shop' => ['backend' => ['home_status' => ['orders' => HomeStatusTestItem::class, 'errors' => false]]],
        'bad' => ['backend' => ['home_status' => ['nope' => stdClass::class]]],
    ];

    return array_keys(HomeStatus::sources($configs)) === ['scheduler', 'orders'];
});

check('il sito ha l\'ultima parola e una voce che fallisce sparisce', function () {
    HomeStatus::set('scheduler', false);
    HomeStatus::set('broken', HomeStatusFailingItem::class);
    $configs = ['shop' => ['backend' => ['home_status' => ['orders' => HomeStatusTestItem::class, 'errors' => false]]]];
    $items = HomeStatus::items(['admin'], '/backend', $configs);
    $keys = array_keys(HomeStatus::sources($configs));
    HomeStatus::reset();

    return $keys === ['orders', 'broken']
        && array_map(static fn (StatusCard $card) => $card->getSchema('title'), $items) === ['Ordini']
        && !array_key_exists('broken', HomeStatus::sources([]));
});
