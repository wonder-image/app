<?php
/** php tests/Docs/SecurityDocTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

$doc = (string) file_get_contents(__DIR__ . '/../../docs/app/piattaforma/sicurezza.md');
preg_match('/```nginx\n(.*?)```/s', $doc, $block);
$snippet = $block[1] ?? '';

/** Regex delle location di primo livello con `deny all`, nell'ordine dello snippet. */
function nginxDenyRules(string $snippet): array {
    preg_match_all('/^location (~\*?) (.+) \{ deny all; \}$/m', $snippet, $m, PREG_SET_ORDER);
    return array_map(fn (array $r) => '#' . str_replace('#', '\#', $r[2]) . '#' . ($r[1] === '~*' ? 'i' : ''), $m);
}

function nginxDenied(string $snippet, string $uri): bool {
    foreach (nginxDenyRules($snippet) as $re) {
        if (preg_match($re, $uri) === 1) { return true; }
    }
    return false;
}

check('M2: nginx nega data/ di xml-sitemaps tranne txt/html/gz/immagini, pages/ e *.conf', function () use ($snippet) {
    $base = '/vendor/wonder-image/app/vendor-static/xml-sitemaps/';
    foreach (['data/', 'data/crawl_state.dat', 'data/sess_abc', 'data/sitemap.xml', 'pages/class.utils.inc.php', 'default.conf', 'DATA/x.dat'] as $p) {
        if (!nginxDenied($snippet, $base . $p)) { echo "    non negato: $p\n"; return false; }
    }
    foreach (['index.php', 'documentation.html', 'data/sitemap.xml.gz', 'data/urllist.txt', 'data/logo.png'] as $p) {
        if (nginxDenied($snippet, $base . $p)) { echo "    negato per errore: $p\n"; return false; }
    }
    $data = strpos($snippet, 'xml-sitemaps/data/');
    $vendor = strpos($snippet, 'location ~* ^/vendor(/|$)');
    return $data !== false && $vendor !== false && $data < $vendor;
});

check('N4: nginx nega log ruotati, backup e archivi fuori da assets/upload', function () use ($snippet) {
    foreach (['/error.log.1', '/x.old', '/x.orig', '/.index.php.swp', '/index.php~', '/backup.zip', '/site.tar.gz', '/db.SQL'] as $p) {
        if (!nginxDenied($snippet, $p)) { echo "    non negato: $p\n"; return false; }
    }
    foreach (['/assets/upload/documents/a.zip', '/assets/0.0/css/old.css', '/index.php', '/vendor/wonder-image/app/resources/assets/logos/a.png'] as $p) {
        if (nginxDenied($snippet, $p)) { echo "    negato per errore: $p\n"; return false; }
    }
    return true;
});

check('M3 e M7: doc spiega geolocation=(self) e il gruppo condiviso dei log', function () use ($doc) {
    return str_contains($doc, 'watchPosition') && str_contains($doc, 'GoogleMap')
        && str_contains($doc, 'stesso gruppo') && str_contains($doc, 'NON VERIFICATO');
});

summary();
