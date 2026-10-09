<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\Auth\Frontend\AccountPagination;

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

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures)."\n");
    exit(1);
}

echo "Account pagination: OK\n";
