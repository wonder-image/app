<?php
/** php tests/Backend/Support/HomeWidgetsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Contracts\HomeWidget;
use Wonder\Backend\Support\HomeWidgets;

final class PrimiPassiWidgetDiProva implements HomeWidget
{
    public function title(): string { return 'Primi passi'; }
    public function render(): string { return '<div class="primi-passi">manca la P.IVA</div>'; }
    public function authorities(): array { return ['admin']; }
    public function order(): int { return 10; }
}

final class DaControllareWidgetDiProva implements HomeWidget
{
    public function title(): string { return 'Da controllare'; }
    public function render(): string { return '<div class="da-controllare">2 errori</div>'; }
    public function authorities(): array { return ['admin', 'administrator']; }
    public function order(): int { return 5; }
}

final class WidgetRottoDiProva implements HomeWidget
{
    public function title(): string { return 'Rotto'; }
    public function render(): string { throw new RuntimeException('boom'); }
    public function authorities(): array { return []; }
    public function order(): int { return 1; }
}

$configs = [
    'gestionale' => ['backend' => ['home_widgets' => [
        PrimiPassiWidgetDiProva::class,
        DaControllareWidgetDiProva::class,
    ]]],
    'altro' => ['backend' => ['home_widgets' => ['Classe\\Che\\Non\\Esiste', 42]]],
];

check('riquadri ordinati per order', function () use ($configs) {
    $widgets = HomeWidgets::fromConfigs($configs, ['admin']);

    return array_map(static fn (HomeWidget $w): string => $w->title(), $widgets)
        === ['Da controllare', 'Primi passi'];
});

check('filtro per ruolo', function () use ($configs) {
    $widgets = HomeWidgets::fromConfigs($configs, ['administrator']);

    return count($widgets) === 1 && $widgets[0]->title() === 'Da controllare';
});

check('senza ruoli dichiarati il riquadro si vede sempre', function () {
    $widgets = HomeWidgets::fromConfigs(
        ['x' => ['backend' => ['home_widgets' => [WidgetRottoDiProva::class]]]],
        ['administrator']
    );

    return count($widgets) === 1;
});

check('classi non valide ignorate senza eccezioni', function () use ($configs) {
    return count(HomeWidgets::fromConfigs($configs, ['admin'])) === 2;
});

check('renderAll unisce il markup dei riquadri', function () use ($configs) {
    $html = HomeWidgets::renderAll(['admin'], $configs);

    return str_contains($html, 'da-controllare')
        && str_contains($html, 'primi-passi')
        && strpos($html, 'da-controllare') < strpos($html, 'primi-passi');
});

check('un riquadro che solleva non rompe la home', function () {
    $html = HomeWidgets::renderAll(
        ['admin'],
        ['x' => ['backend' => ['home_widgets' => [WidgetRottoDiProva::class, PrimiPassiWidgetDiProva::class]]]]
    );

    return str_contains($html, 'primi-passi') && !str_contains($html, 'boom');
});

summary();
