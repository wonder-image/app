<?php
/** php tests/OAuth/LangTest.php */
declare(strict_types=1);

require __DIR__ . '/../harness.php';

$flatten = static function (array $a, string $p = '') use (&$flatten): array {
    $out = [];
    foreach ($a as $k => $v) { is_array($v) ? $out += $flatten($v, $p.$k.'.') : $out[$p.$k] = $v; }
    return $out;
};
$it = $flatten(json_decode((string) file_get_contents(__DIR__.'/../../resources/lang/it/oauth.json'), true) ?? []);
$en = $flatten(json_decode((string) file_get_contents(__DIR__.'/../../resources/lang/en/oauth.json'), true) ?? []);

check('it ed en hanno le stesse chiavi', fn () => array_keys($it) == array_keys($en) && count($it) > 0);
check('nessuna stringa vuota', fn () => !in_array('', $it, true) && !in_array('', $en, true));
check('segnaposto solo nella forma {{nome}}', function () use ($it, $en) {
    foreach (array_merge(array_values($it), array_values($en)) as $v) {
        // un `{` non preceduto da `{` e non seguito da `{` è un segnaposto a graffa singola
        if (preg_match('/(?<!\{)\{(?!\{)[a-z_]+\}/', (string) $v)) { return false; }
    }
    return true;
});
check('stessi segnaposto in it ed en', function () use ($it, $en) {
    foreach ($it as $k => $v) {
        preg_match_all('/\{\{([a-z_]+)\}\}/', (string) $v, $a);
        preg_match_all('/\{\{([a-z_]+)\}\}/', (string) ($en[$k] ?? ''), $b);
        sort($a[1]); sort($b[1]);
        if ($a[1] !== $b[1]) { return false; }
    }
    return true;
});

$used = [];
foreach (array_merge(glob(__DIR__.'/../../app/view/pages/oauth/*.php'), glob(__DIR__.'/../../app/view/pages/backend/oauth/*.php') ?: [], glob(__DIR__.'/../../app/http/oauth/*.php'), glob(__DIR__.'/../../class/App/Resources/OAuth/*.php') ?: []) as $f) {
    preg_match_all("/'oauth\.([a-z0-9_.]+)'/", (string) file_get_contents($f), $m);
    $used = array_merge($used, $m[1]);
}
check('ogni chiave usata esiste in it ed en', function () use ($used, $it, $en) {
    $missing = array_unique(array_merge(array_diff(array_unique($used), array_keys($it)), array_diff(array_unique($used), array_keys($en))));
    if ($missing) { echo '    mancanti: '.implode(', ', $missing)."\n"; }
    return $missing === [];
});

summary();
