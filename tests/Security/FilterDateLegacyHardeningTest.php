<?php
/**
 * Test autonomo (niente PHPUnit nel repo):
 *   php tests/Security/FilterDateLegacyHardeningTest.php
 *
 * filterDate() di app/function/backend/filter.php e' il filtro per data
 * legacy: il package non la chiama piu' (le liste passano da
 * Table::filterDate() e dalla classe FilterDate), ma i siti legacy possono
 * ancora usarla. Metteva wi-from, wi-to e wi-year nell'SQL senza controlli e
 * la query string senza escape negli href dei mesi e nei campi nascosti del
 * form. Deve validare come FilterDate: date gg/mm/aaaa valide, mese e anno
 * interi, condizione da FilterDate::buildCondition(), link e campi escapati.
 *
 * Le funzioni SQL del core sono stub e sqlSelect() registra la query: il test
 * legge l'SQL che arriverebbe al database. I primi test fissano l'output per i
 * dati legittimi.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
require __DIR__ . '/../../app/function/string/date.php';
require __DIR__ . '/../../app/function/backend/filter.php';

// Date::month() traduce con __t(), che vive in app/function/helper.php (fuori dall'autoload).
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        return $key;
    }
}

// Stub di app/function/sql.php: nessun database, sqlSelect() tiene l'ultima query.
function sqlTableInfo(...$args): object
{
    return (object) ['create_time' => date('Y-m-d H:i:s', strtotime('-8 months'))];
}

function sqlCount(...$args): int
{
    return 0;
}

function sqlSelect(...$args): object
{
    $GLOBALS['__query'] = $args[1] ?? null;

    return (object) ['Nrow' => 0, 'row' => [], 'exists' => false];
}

function sqlColumnExists(...$args): bool
{
    return false;
}

// I valori non validi vanno ignorati in silenzio: un warning o un notice fa fallire il test.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

/**
 * filterDate() su una richiesta con questi parametri: $_GET e
 * $_SERVER['QUERY_STRING'] arrivano dalla stessa query string, come in PHP.
 */
function filtro(array $parametri, array $globali = []): object
{
    foreach (['FILTER_COLUMN', 'HOW_MANY_DAYS', 'QUERY_CUSTOM'] as $nome) {
        $GLOBALS[$nome] = $globali[$nome] ?? null;
    }

    $GLOBALS['NAME'] = (object) ['table' => 'prova'];
    $GLOBALS['TEXT'] = (object) ['titleP' => 'articoli', 'all' => 'tutti', 'article' => 'gli'];
    $GLOBALS['__query'] = null;

    $_SERVER['QUERY_STRING'] = http_build_query($parametri, '', '&');
    parse_str($_SERVER['QUERY_STRING'], $get);
    $_GET = $get;

    return filterDate();
}

/** Condizione attesa per l'anno intero. */
function annoIntero(string $anno): string
{
    return "`deleted` = 'false' AND `creation` BETWEEN '$anno-01-01 00:00:00' AND '$anno-12-31 23:59:59' ";
}

/** Parametri di ogni link dei mesi, decodificati come li legge il browser. */
function linkMesi(string $html): array
{
    preg_match_all("/<a href='([^']*)'/", $html, $matches);

    return array_map(static function (string $href): array {
        parse_str(ltrim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '?'), $params);

        return $params;
    }, $matches[1]);
}

function ogniLink(string $html, callable $condizione): bool
{
    $links = linkMesi($html);

    return $links !== [] && count(array_filter($links, $condizione)) === count($links);
}

/** Parametri che il form invia con i campi nascosti, letti come li legge PHP. */
function campiNascosti(string $html): array
{
    preg_match_all("/<input type='hidden' name='([^']*)' value='([^']*)'>/", $html, $matches, PREG_SET_ORDER);

    $coppie = array_map(static fn (array $campo): string =>
        urlencode(html_entity_decode($campo[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        .'='.urlencode(html_entity_decode($campo[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        $matches);

    parse_str(implode('&', $coppie), $params);

    return $params;
}

echo "Dati legittimi\n";

check('intervallo valido: SQL identico a prima, byte per byte', function () {
    $filtro = filtro(['wi-from' => '01/02/2026', 'wi-to' => '15/02/2026']);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' "
        && $filtro->query === $filtro->query_all."ORDER BY `creation` DESC "
        && $GLOBALS['__query'] === $filtro->query
        && $filtro->title === 'Articoli dal 01/02/2026 al 15/02/2026'
        && $filtro->from === '01/02/2026'
        && $filtro->to === '15/02/2026'
        && str_contains($filtro->html, "name='wi-from' value='01/02/2026'")
        && str_contains($filtro->html, "name='wi-to' value='15/02/2026'");
});

check('senza GET: ultimi 31 giorni, come prima', function () {
    $filtro = filtro([]);

    return $filtro->title === 'Articoli ultimi 31 giorni'
        && $filtro->query_all === "`deleted` = 'false' AND `creation` BETWEEN '".date('Y-m-d', strtotime('-30 days'))." 00:00:00' AND '".date('Y-m-d')." 23:59:59' ";
});

check('colonna e giorni dalla configurazione del sito, come prima', function () {
    $filtro = filtro([], ['FILTER_COLUMN' => 'date', 'HOW_MANY_DAYS' => 0]);

    return $filtro->title === 'Articoli di oggi'
        && $filtro->query_all === "`deleted` = 'false' AND `date` BETWEEN '".date('Y-m-d')." 00:00:00' AND '".date('Y-m-d')." 23:59:59' ";
});

check('$QUERY_CUSTOM resta in testa alla query', function () {
    $filtro = filtro(['wi-from' => '01/02/2026', 'wi-to' => '15/02/2026'], ['QUERY_CUSTOM' => "`type` = 'news'"]);

    return $filtro->query_all === "`type` = 'news' AND `deleted` = 'false' AND `creation` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' ";
});

check('solo anno: l\'anno intero, come prima', function () {
    $filtro = filtro(['wi-year' => '2025']);

    return $filtro->query_all === annoIntero('2025')
        && $filtro->title === 'Articoli del 2025'
        && $filtro->from === '01/01/2025'
        && $filtro->to === '31/12/2025';
});

check('array->month e array->year nel formato di prima', function () {
    $filtro = filtro([]);

    return ($filtro->array->month[0] ?? null) === date('F Y')
        && ($filtro->array->year[0] ?? null) === date('Y');
});

echo "\nMese e anno\n";

check('link dei mesi con mese numerico e & codificato', function () {
    $filtro = filtro([]);

    return str_contains($filtro->html, "href='?wi-month=".date('n')."&amp;wi-year=".date('Y')."'");
});

check('mese numerico corrente: il mese intero, bottone attivo', function () {
    $filtro = filtro(['wi-month' => date('n'), 'wi-year' => date('Y')]);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` BETWEEN '".date('Y-m-01')." 00:00:00' AND '".date('Y-m-t')." 23:59:59' "
        && $filtro->title === 'Articoli di date.month.'.strtolower(date('F')).' '.date('Y')
        && $filtro->from === date('01/m/Y')
        && $filtro->to === date('t/m/Y')
        && str_contains($filtro->html, "class='btn btn-dark btn-sm col'");
});

check('mese con il nome inglese (vecchio link): resta l\'anno intero', function () {
    $filtro = filtro(['wi-month' => date('F'), 'wi-year' => date('Y')]);

    return $filtro->query_all === annoIntero(date('Y')) && $filtro->title === 'Articoli del '.date('Y');
});

foreach ([date('n').' OR 1=1', '13', '0', ' '.date('n'), date('n').'.0'] as $mese) {
    check('mese non valido ignorato, resta l\'anno: wi-month='.var_export($mese, true), function () use ($mese) {
        $filtro = filtro(['wi-month' => $mese, 'wi-year' => date('Y')]);

        return $filtro->query_all === annoIntero(date('Y')) && $filtro->title === 'Articoli del '.date('Y');
    });
}

foreach (['99999', '0999', date('Y').' OR 1=1', date('Y').'.0'] as $anno) {
    check('anno non valido ignorato, restano gli ultimi giorni: wi-year='.var_export($anno, true), function () use ($anno) {
        $base = filtro([]);
        $filtro = filtro(['wi-month' => date('n'), 'wi-year' => $anno]);

        return $filtro->query_all === $base->query_all && $filtro->title === $base->title;
    });
}

echo "\nSQL injection\n";

check('apice in wi-from: valore scartato, resta la condizione su wi-to', function () {
    $filtro = filtro(['wi-from' => "01/01/2026' OR '1'='1", 'wi-to' => '31/01/2026']);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` <= '2026-01-31 23:59:59' "
        && $filtro->title === 'Articoli fino al 31/01/2026';
});

check('UNION in coda a wi-to: valore scartato, resta la condizione su wi-from', function () {
    $filtro = filtro(['wi-from' => '01/02/2026', 'wi-to' => "15/02/2026' UNION SELECT password FROM user -- "]);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` >= '2026-02-01 00:00:00' "
        && $filtro->title === 'Articoli dal 01/02/2026';
});

check('payload in entrambe le date: restano gli ultimi giorni', function () {
    $base = filtro([]);
    $filtro = filtro(['wi-from' => "1' OR 1=1 -- /x/y", 'wi-to' => "2' OR 1=1 -- /x/y"]);

    return $filtro->query_all === $base->query_all && $filtro->title === $base->title;
});

check('payload in wi-year senza mese: anno scartato', function () {
    $base = filtro([]);
    $filtro = filtro(['wi-year' => "2026' OR '1'='1"]);

    return $filtro->query_all === $base->query_all && $filtro->title === $base->title;
});

check('nome colonna passato da Query::escapeIdentifier()', function () {
    $filtro = filtro(['wi-from' => '01/02/2026', 'wi-to' => '15/02/2026'], ['FILTER_COLUMN' => 'creation` = 1 OR `x']);

    return $filtro->query_all === "`deleted` = 'false' AND `creation`` = 1 OR ``x` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' ";
});

echo "\nXSS\n";

check('markup in wi-from: niente markup nei campi, nel titolo e in ->from', function () {
    $filtro = filtro(['wi-from' => "01/01/2026'><img src=x onerror=alert(1)>", 'wi-to' => '31/01/2026']);

    return !str_contains($filtro->html, '<img')
        && !str_contains($filtro->title, '<')
        && $filtro->from === ''
        && str_contains($filtro->html, "name='wi-from' value=''");
});

check('markup nei valori della query string: link e campi nascosti codificati, valori intatti', function () {
    $redirect = "x'><script>alert(1)</script>";
    $id = "5' onmouseover='alert(1)";
    $filtro = filtro(['redirect' => $redirect, 'id' => $id]);

    return !str_contains($filtro->html, '<script>alert(1)</script>')
        && ogniLink($filtro->html, fn (array $params) => ($params['redirect'] ?? null) === $redirect && ($params['id'] ?? null) === $id)
        && campiNascosti($filtro->html) === ['redirect' => $redirect, 'id' => $id];
});

check('markup nella chiave di un parametro: chiave codificata e conservata', function () {
    $chiave = "x'><svg/onload=alert(1)>";
    $filtro = filtro([$chiave => '1']);

    return !str_contains($filtro->html, '<svg')
        && campiNascosti($filtro->html) === [$chiave => '1'];
});

echo "\nAltri parametri della query string\n";

check('restano nei link e nei campi nascosti, senza date e wi-limit', function () {
    $filtro = filtro(['q' => 'rossi', 'wi-limit' => '50', 'wi-from' => '01/02/2026', 'wi-to' => '15/02/2026']);

    return ogniLink($filtro->html, fn (array $params) => array_keys($params) === ['wi-month', 'wi-year', 'q'] && $params['q'] === 'rossi')
        && campiNascosti($filtro->html) === ['q' => 'rossi'];
});

check('filtri a checkbox (array): valori conservati', function () {
    $filtro = filtro(['stato' => ['attivo', 'sospeso']]);

    return ogniLink($filtro->html, fn (array $params) => ($params['stato'] ?? null) === ['attivo', 'sospeso'])
        && campiNascosti($filtro->html) === ['stato' => ['attivo', 'sospeso']];
});

check('array annidati: nessun warning, valori conservati', function () {
    $valore = ['stato' => ['attivo'], 'tipo' => 'news'];
    $filtro = filtro(['f' => $valore]);

    return ogniLink($filtro->html, fn (array $params) => ($params['f'] ?? null) === $valore)
        && campiNascosti($filtro->html) === ['f' => $valore];
});

echo "\nValori non validi (ignorati senza warning)\n";

check('array al posto di date, mese e anno: ignorati', function () {
    $base = filtro([]);
    $filtro = filtro(['wi-from' => ['x'], 'wi-to' => ['y'], 'wi-month' => ['9'], 'wi-year' => ['2026']]);

    return $filtro->query_all === $base->query_all && $filtro->title === $base->title;
});

check('solo wi-from valido: condizione >= e titolo "dal ..."', function () {
    $filtro = filtro(['wi-from' => '01/02/2026']);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` >= '2026-02-01 00:00:00' "
        && $filtro->title === 'Articoli dal 01/02/2026'
        && str_contains($filtro->html, "name='wi-to' value=''");
});

check('solo wi-to valido: condizione <= e titolo "fino al ..."', function () {
    $filtro = filtro(['wi-to' => '15/02/2026']);

    return $filtro->query_all === "`deleted` = 'false' AND `creation` <= '2026-02-15 23:59:59' "
        && $filtro->title === 'Articoli fino al 15/02/2026'
        && str_contains($filtro->html, "name='wi-from' value=''");
});

check('data inesistente (31/02) e byte NUL: scartate', function () {
    $base = filtro([]);
    $filtro = filtro(['wi-from' => '31/02/2026', 'wi-to' => "15/02/2026\0"]);

    return $filtro->query_all === $base->query_all && $filtro->title === $base->title;
});

summary();
