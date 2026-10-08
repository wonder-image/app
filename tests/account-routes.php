<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\Auth\Frontend\AccountPanel;
use Wonder\Auth\Frontend\AccountRoutes;
use Wonder\Auth\Frontend\BaseAccountExtension;
use Wonder\Http\Route;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

function __t(string $key, array $replace = []): string { return $key; }
function __r(string $name, array $parameters = []): string { return Route::url($name, $parameters); }

final class ProvaExtension extends BaseAccountExtension
{
    public function routes(): void
    {
        AccountRoutes::group(static function (): void {
            Route::get('/prova/', AccountRoutes::handler(), ['account_action' => 'prova'])->name('prova');
        });
    }
    public function navigation(array $items, object $user): array
    {
        unset($items['billing']);
        $items['prova'] = ['label' => 'Prova', 'href' => \__r('account.prova'), 'icon' => 'bi bi-star'];
        $items['vuota'] = ['label' => 'Vuota', 'href' => ''];
        return $items;
    }
    public function personalRows(array $rows, object $user): array { $rows[] = ['key' => 'prova']; return $rows; }
    public function validatePersonal(array $input, object $user): array { return ['errore prova']; }
    public function personalUserValues(array $input, object $user): array { return ['prova' => 1]; }
    public function head(): string { return '<link rel="stylesheet" href="/prova.css">'; }
}

final class SenzaIndirizzi extends AccountPanel
{
    public function sections(): array { return ['overview', 'personal', 'billing']; }
}

$fresh = static function (): void { Route::reset(); AccountRoutes::reset(); };
$byName = static function (): array {
    $out = [];
    foreach (Route::all() as $route) {
        if (($route['name'] ?? '') !== '') { $out[$route['name']] = $route; }
    }
    return $out;
};
$posts = static fn (): array => array_values(array_map(static fn ($r) => $r['path'], array_filter(Route::all(), static fn ($r) => strtoupper((string) $r['method']) === 'POST')));
$user = (object) ['id' => 1, 'name' => 'Ada'];

// Rotte del core, nomi e percorsi di §4.1.
$fresh();
AccountRoutes::register(new AccountPanel());
$routes = $byName();
$expected = [
    'account.index' => '/account/',
    'account.personal' => '/account/dati-personali/',
    'account.email.confirm' => '/account/email/conferma/',
    'account.addresses' => '/account/indirizzi/',
    'account.addresses.create' => '/account/indirizzi/nuovo/',
    'account.addresses.edit' => '/account/indirizzi/{id}/',
    'account.addresses.delete' => '/account/indirizzi/{id}/elimina/',
    'account.billing' => '/account/fatturazione/',
];
foreach ($expected as $name => $path) {
    $check(($routes[$name]['path'] ?? null) === $path, "{$name} deve stare su {$path}");
    $public = $name === 'account.email.confirm';
    $check((bool) ($routes[$name]['private'] ?? false) === !$public, "{$name}: protezione sbagliata");
    if (!$public) {
        $check(($routes[$name]['permit'] ?? null) === ['client'], "{$name} deve permettere solo client");
    }
}
foreach (['/account/dati-personali/', '/account/indirizzi/nuovo/', '/account/indirizzi/{id}/', '/account/indirizzi/{id}/elimina/', '/account/fatturazione/'] as $path) {
    $check(in_array($path, $posts(), true), "manca il POST di {$path}");
}

// Chiamate ripetute: innocue con la stessa classe, errore con un'altra.
$count = count(Route::all());
AccountRoutes::register(new AccountPanel());
$check(count(Route::all()) === $count, 'register ripetuto non deve duplicare le route');
try {
    AccountRoutes::register(new SenzaIndirizzi());
    $check(false, 'una classe diversa deve lanciare LogicException');
} catch (\LogicException) {
}

// Estensione prima e dopo register, una sola volta.
foreach (['prima', 'dopo'] as $when) {
    $fresh();
    if ($when === 'prima') { AccountRoutes::extend(new ProvaExtension()); AccountRoutes::extend(new ProvaExtension()); }
    AccountRoutes::register(new AccountPanel());
    if ($when === 'dopo') { AccountRoutes::extend(new ProvaExtension()); AccountRoutes::extend(new ProvaExtension()); }
    $prova = array_values(array_filter(Route::all(), static fn ($r) => $r['path'] === '/account/prova/'));
    $check(count($prova) === 1, "estensione {$when}: /account/prova/ una volta sola");
    $check((bool) ($prova[0]['private'] ?? false) && ($prova[0]['permit'] ?? null) === ['client'], "estensione {$when}: route protetta");
}

// Menu: ordine, voce attiva, aggiunte e tolte dell'estensione, niente voci vuote.
$items = AccountRoutes::panel()->navigation($user, 'personal');
$check(array_keys($items) === ['overview', 'personal', 'addresses', 'prova'], 'ordine del menu sbagliato: '.implode(',', array_keys($items)));
$check(($items['personal']['active'] ?? false) === true && ($items['overview']['active'] ?? true) === false, 'voce attiva sbagliata');
$check(($items['overview']['icon'] ?? '') === 'bi bi-house' && ($items['prova']['href'] ?? '') !== '', 'icone o href mancanti');

// Hook a cascata.
$panel = AccountRoutes::panel();
$check(count($panel->personalRows([], $user)) === 1, 'personalRows non passa dalle estensioni');
$check($panel->validatePersonal([], $user) === ['errore prova'], 'validatePersonal non raccoglie i messaggi');
$check($panel->personalUserValues([], $user) === ['prova' => 1], 'personalUserValues non unisce i valori');
$check(str_contains($panel->head(), '/prova.css'), 'head non concatena');

// Sezione spenta: né route né voce.
$fresh();
AccountRoutes::register(new SenzaIndirizzi());
$check(!isset($byName()['account.addresses']) && !isset($byName()['account.addresses.delete']), 'indirizzi spenti: non devono esserci route');
$check(!isset(AccountRoutes::panel()->navigation($user)['addresses']), 'indirizzi spenti: non deve esserci la voce');
$check(isset($byName()['account.index']), 'account.index c\'è sempre');

if ($failures !== []) {
    echo implode("\n", $failures)."\n";
    exit(1);
}
echo "account-routes: ok\n";
