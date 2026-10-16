<?php
/** php tests/Backend/Support/HomeNavigationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\{Model, Resource};
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Backend\Contracts\{HomeLinkSummary, HomeStatusItem};
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
            ['title' => 'Riepilogo', 'href' => 'https://s.test/backend/app/scheduler', 'group' => '', 'badge' => '', 'detail' => ''],
            ['title' => 'Pianificazioni', 'href' => 'https://s.test/backend/app/scheduler/schedules', 'group' => 'Automazioni', 'badge' => '', 'detail' => ''],
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

final class HomeLinkTestSummary implements HomeLinkSummary
{
    public function summary(array $authorities): ?array
    {
        return ['badge' => '12', 'detail' => '8 in vendita · <b>4</b> in affitto'];
    }
}

final class HomeLinkEmptySummary implements HomeLinkSummary
{
    public function summary(array $authorities): ?array
    {
        return null;
    }
}

final class HomeLinkFailingSummary implements HomeLinkSummary
{
    public function summary(array $authorities): ?array
    {
        throw new RuntimeException('conteggio giù');
    }
}

check('home_summary dà badge e riga ai link; null, eccezioni e classi estranee non li toccano', function () {
    $navigation = [
        ['title' => 'Catalogo', 'icon' => 'bi-house', 'subnavs' => [
            ['title' => 'Immobili', 'folder' => 'immobili', 'file' => '', 'home_summary' => HomeLinkTestSummary::class],
            ['title' => 'Vuoto', 'folder' => 'vuoto', 'file' => '', 'home_summary' => HomeLinkEmptySummary::class],
            ['title' => 'Rotto', 'folder' => 'rotto', 'file' => '', 'home_summary' => HomeLinkFailingSummary::class],
            ['title' => 'Estraneo', 'folder' => 'x', 'file' => '', 'home_summary' => stdClass::class],
        ]],
    ];
    $links = HomeNavigation::sections($navigation, ['admin'], '/backend')[0]['links'];

    return array_column($links, 'title') === ['Immobili', 'Vuoto', 'Rotto', 'Estraneo']
        && $links[0]['badge'] === '12' && $links[0]['detail'] === '8 in vendita · <b>4</b> in affitto'
        && array_column(array_slice($links, 1), 'badge') === ['', '', '']
        && array_column(array_slice($links, 1), 'detail') === ['', '', ''];
});

final class HomeLinkTestModel extends Model
{
    public static string $table = 'home_link_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class HomeLinkTestResource extends Resource
{
    public static string $model = HomeLinkTestModel::class;
}

check('NavigationSchema::homeSummary salva solo la classe e rifiuta quelle estranee', function () {
    $schema = NavigationSchema::for(HomeLinkTestResource::class);
    $default = $schema->get('home_summary');
    $stored = $schema->homeSummary(HomeLinkTestSummary::class)->get('home_summary');

    try {
        NavigationSchema::for(HomeLinkTestResource::class)->homeSummary(stdClass::class);
        $rejected = false;
    } catch (RuntimeException) {
        $rejected = true;
    }

    return $default === null && $stored === HomeLinkTestSummary::class && $rejected;
});

check('LinkListCard mostra badge e riga secondaria escapati solo dove ci sono', function () {
    $html = LinkListCard::make('Catalogo')
        ->link('Immobili', '/i', '', '12', '8 in vendita · <b>4</b> in affitto')
        ->link('Agenzie', '/a')
        ->render('bootstrap');

    return str_contains($html, '>12</span>')
        && str_contains($html, '<small class="text-body-secondary">8 in vendita · &lt;b&gt;4&lt;/b&gt; in affitto</small>')
        && !str_contains($html, '<b>4</b>')
        && substr_count($html, 'rounded-pill') === 2
        && str_contains($html, 'gap-2">Agenzie<i class="bi bi-chevron-right');
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
