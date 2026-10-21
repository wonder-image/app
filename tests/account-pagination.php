<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\App\LegacyGlobals;
use Wonder\Auth\Frontend\AccountPagination;
use Wonder\Elements\Frontend\Pagination;
use Wonder\View\View;

// Gli helper del sito, come nello snapshot di tests/fixtures/account-pagination.json.
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function __t(string $key, array $replacements = []): string { return $key.($replacements === [] ? '' : ' '.json_encode($replacements)).' <&>'; }

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$empty = AccountPagination::make(0, 1);
$check($empty['page'] === 1 && $empty['pages'] === 1 && $empty['from'] === 0 && $empty['to'] === 0 && $empty['limit'] === '0, 10', 'Senza righe la pagina è 1 di 1, da 0 a 0.');

$second = AccountPagination::make(12, 2);
$check($second['offset'] === 10 && $second['from'] === 11 && $second['to'] === 12 && $second['limit'] === '10, 10', 'La seconda pagina di 12 va da 11 a 12.');

$check(AccountPagination::make(12, 99)['page'] === 2, 'Una pagina oltre l\'ultima non torna all\'ultima.');
foreach (['0', '-3', 'abc', '', ['x'], null] as $strange) {
    $check(AccountPagination::make(12, $strange)['page'] === 1, 'Una pagina strana ('.json_encode($strange).') non torna alla prima.');
}
$check(AccountPagination::make(25, '2.7')['page'] === 2, 'Una pagina decimale non si tronca.');
$check(AccountPagination::make(30, 3, 10)['to'] === 30, 'L\'ultima pagina piena non finisce sul totale.');

$base = 'https://negozio.test/account/ordini/';
$check(AccountPagination::url($base, 1) === $base, 'La prima pagina ha il parametro.');
$check(AccountPagination::url($base, 3) === $base.'?pagina=3', 'La terza pagina non ha ?pagina=3.');
$check(AccountPagination::url($base.'?x=1', 2) === $base.'?x=1&pagina=2', 'Una base con query non usa &.');

$check(AccountPagination::window(AccountPagination::make(100, 5)) === [3, 4, 5, 6, 7], 'La finestra non è due pagine per lato.');
$check(AccountPagination::window(AccountPagination::make(100, 1)) === [1, 2, 3], 'La finestra esce dalla prima pagina.');
$check(AccountPagination::window(AccountPagination::make(5, 1)) === [1], 'Con una pagina la finestra non è [1].');

$_GET = ['pagina' => '4'];
$check(AccountPagination::requested() === '4', 'requested() non legge ?pagina.');
$_GET = [];
$check(AccountPagination::requested() === 1, 'Senza ?pagina requested() non dà 1.');

// Il componente frontend.account.pagination ora è un wrapper dell'Element:
// stesso markup di prima, spazi tra i tag a parte.
LegacyGlobals::share(['ROOT' => sys_get_temp_dir().'/account-pagination-'.uniqid(), 'ROOT_APP' => dirname(__DIR__).'/app']);
$normalize = static fn (string $html): string => trim((string) preg_replace('/>\s+</', '><', $html));
$snapshot = json_decode((string) file_get_contents(__DIR__.'/fixtures/account-pagination.json'), true);
$baseUrl = '/account/ordini/?x=1&y=<2>';

foreach ($snapshot as [$total, $page, $expected]) {
    $pagination = AccountPagination::make($total, $page);
    $view = View::component('frontend.account.pagination', ['pagination' => $pagination, 'base_url' => $baseUrl]);
    $element = Pagination::account($pagination, $baseUrl)->render('wonder');
    $check($normalize($view) === $expected, "Il componente con {$total} righe a pagina {$page} non ha il markup dello snapshot.");
    $check($element === $expected, "Pagination::account() con {$total} righe a pagina {$page} non ha il markup dello snapshot.");
}

$account = Pagination::account(AccountPagination::make(100, 5), $baseUrl);
$check($account->visiblePages() === AccountPagination::window(AccountPagination::make(100, 5)), 'La finestra dell\'Element non è quella di AccountPagination.');
$check($account->pageUrl(3) === AccountPagination::url($baseUrl, 3) && $account->pageUrl(1) === $baseUrl, 'I link dell\'Element non sono quelli di AccountPagination.');

// Sorgente generica: pagina, totale pagine e link, senza conti né SQL.
$generic = Pagination::make(3, 12)->url('/blog/?tag=a&b')->render('wonder');
$check(str_starts_with($generic, '<ul class="wi-pagination" aria-label="Pagine">') && !str_contains($generic, 'wi-row-table__foot'), 'Senza riepilogo la paginazione generica non è il solo elenco.');
$check(str_contains($generic, 'href="/blog/?tag=a&amp;b&amp;page=4" aria-label="Pagina successiva"') && str_contains($generic, 'href="/blog/?tag=a&amp;b" aria-current') === false, 'La base generica non aggiunge &page=N.');
$check(str_contains($generic, 'href="/blog/?tag=a&amp;b&amp;page=3" aria-current="page">3</a>') && str_contains($generic, '>1</a>') && str_contains($generic, '>5</a>') && !str_contains($generic, '>6</a>'), 'La finestra generica non è due pagine per lato.');
$check(Pagination::make(1, 3)->url('/blog/')->pageUrl(1) === '/blog/', 'La prima pagina generica non è la base.');

$callable = Pagination::make(2, 4)->url(static fn (int $page): string => "/p/{$page}/")->window(1)->label('Archivio <x>')->previousLabel('Prima')->nextLabel('Dopo')->addClass('mt-4')->render('wonder');
$check(str_starts_with($callable, '<ul class="wi-pagination mt-4" aria-label="Archivio &lt;x&gt;">'), 'Classe aggiunta o etichetta escapata mancanti sull\'elenco.');
$check(str_contains($callable, 'href="/p/1/" aria-label="Prima"') && str_contains($callable, 'href="/p/3/" aria-label="Dopo"') && !str_contains($callable, '>4</a>'), 'Funzione di link, etichette o finestra personalizzate ignorate.');

// Base a stringa: via il `page` che ha già, gli altri parametri restano, il frammento torna in coda.
$links = static fn (string $base): array => [Pagination::make(1, 5)->url($base)->pageUrl(1), Pagination::make(1, 5)->url($base)->pageUrl(2)];
$check($links('/c/') === ['/c/', '/c/?page=2'], 'Una base semplice non dà /c/ e /c/?page=2.');
$check($links('/c/?page=3&q=a&f%5B%5D=x') === ['/c/?q=a&f%5B%5D=x', '/c/?q=a&f%5B%5D=x&page=2'], 'Il page già nella query non si toglie o gli altri parametri cambiano.');
$check($links('/c/?q=a&page=3') === ['/c/?q=a', '/c/?q=a&page=2'], 'Un page in coda alla query non si toglie.');
$check($links('/c/?page=3') === ['/c/', '/c/?page=2'], 'Tolto il page, la prima pagina tiene un ? vuoto.');
$check($links('/c/#lista') === ['/c/#lista', '/c/?page=2#lista'], 'Con un frammento la query non va prima del #.');
$check($links('/c/?page=1&q=a#lista') === ['/c/?q=a#lista', '/c/?q=a&page=2#lista'], 'Con query e frammento i link non sono ricostruiti bene.');
$check(Pagination::make(1, 5)->url('/c/?pagina=4&q=a', 'pagina')->pageUrl(3) === '/c/?q=a&pagina=3', 'Il nome del parametro scelto non si toglie dalla base.');

// Senza url(): ?page=N anche per la prima pagina, mai un ? vuoto.
$check(Pagination::make(2, 3)->pageUrl(1) === '?page=1' && Pagination::make(2, 3)->pageUrl(3) === '?page=3', 'Senza url() la prima pagina non è ?page=1.');

// Una pagina sola (o nessuna): la generica non stampa nulla, a meno di hidden(false).
$check(Pagination::make(1, 1)->render('wonder') === '' && Pagination::make(9, 0)->render('wonder') === '', 'Con una pagina sola la paginazione generica stampa qualcosa.');
$single = Pagination::make(9, 1)->hidden(false)->render('wonder');
$check(substr_count($single, 'aria-disabled="true"') === 2 && str_contains($single, 'href="?page=1" aria-current="page">1</a>'), 'Mostrata con hidden(false), una pagina sola non ha le frecce spente.');
$check(Pagination::make(2, 3)->summary('2 di 3 <ok>')->render('wonder') !== '' && str_starts_with(Pagination::make(2, 3)->summary('2 di 3 <ok>')->render('wonder'), '<div class="wi-row-table__foot"><span>2 di 3 &lt;ok&gt;</span><ul class="wi-pagination"'), 'Il riepilogo generico non apre il piede.');
$check(Pagination::make(2, 3)->hidden()->render('wonder') === '', 'Una paginazione nascosta stampa qualcosa.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}

echo "Account pagination: OK\n";
