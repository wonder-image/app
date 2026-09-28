<?php
/**
 * Test autonomo (niente PHPUnit nel repo):
 *   php tests/Security/FilterDateHardeningTest.php
 *
 * FilterDate costruisce la condizione SQL del filtro per data al render della
 * pagina, partendo da $_GET (date_from, date_to, month, year). Poi
 * Table::buildConfig() firma quella stringa con ConfigCodec: la firma ne
 * garantisce l'integrita', non la sicurezza, quindi un valore iniettato qui
 * arriva firmato fino a SSP. Le date vanno validate (gg/mm/aaaa) prima di
 * entrare nell'SQL, mese e anno devono essere interi e ogni valore che finisce
 * nell'HTML (campi data, link dei mesi, titolo) va escapato.
 *
 * I primi test fissano l'output byte per byte per i dati legittimi.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Backend\Filter\FilterDate;

// Date::month() traduce con __t(), che vive in app/function/helper.php (fuori dall'autoload).
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        return $key;
    }
}

// I valori non validi vanno ignorati in silenzio: un warning o un notice fa fallire il test.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

/** Il filtro vero, senza database: cambia solo la data del primo record (otto mesi fa). */
function filtro(array $get, array $customGet = []): FilterDate
{
    $_GET = $get;

    return new class('prova', mysqli_init(), 30, 'creation', $customGet) extends FilterDate {
        protected function firstDate(): DateTime
        {
            return new DateTime('-8 months');
        }
    };
}

/** Parametri di ogni link dei mesi, decodificati come li legge il browser. */
function linkMesi(string $html): array
{
    preg_match_all('/<a href="([^"]*)"/', $html, $matches);

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

echo "Condizione SQL (FilterDate::buildCondition)\n";

check('intervallo valido: SQL identico a prima, byte per byte', fn () =>
    FilterDate::buildCondition('creation', '01/02/2026', '15/02/2026')
        === "`creation` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' "
);

check('solo "dal" valido: condizione >= sul primo giorno', fn () =>
    FilterDate::buildCondition('creation', '01/02/2026', '')
        === "`creation` >= '2026-02-01 00:00:00' "
);

check('solo "al" valido: condizione <= sull\'ultimo giorno', fn () =>
    FilterDate::buildCondition('creation', null, '15/02/2026')
        === "`creation` <= '2026-02-15 23:59:59' "
);

check('nessuna data valida: nessuna condizione', fn () =>
    FilterDate::buildCondition('creation', '', null) === ''
);

check('apice in date_from: valore scartato, fuori dall\'SQL', fn () =>
    FilterDate::buildCondition('creation', "01/02/2026' OR '1'='1", '15/02/2026')
        === "`creation` <= '2026-02-15 23:59:59' "
);

check('payload in coda a date_to: valore scartato', fn () =>
    FilterDate::buildCondition('creation', '01/02/2026', "15/02/2026' UNION SELECT password FROM user -- ")
        === "`creation` >= '2026-02-01 00:00:00' "
);

$nonValide = [
    '1/2/2026' => 'senza zeri iniziali',
    '01/02/26' => 'anno a due cifre',
    '2026-02-01' => 'formato ISO',
    '01-02-2026' => 'trattini',
    '31/02/2026' => 'giorno inesistente',
    '29/02/2026' => '29 febbraio non bisestile',
    '00/01/2026' => 'giorno zero',
    '01/00/2026' => 'mese zero',
    '01/13/2026' => 'mese 13',
    '01/02/2026 ' => 'spazio in coda',
    "01/02/2026\n" => 'a capo in coda',
    ' 01/02/2026' => 'spazio in testa',
    "'01/02/2026" => 'apice in testa',
    "01/02/2026\0" => 'byte NUL in coda',
    '01/02/0999' => 'anno sotto il 1000 (fuori da DATETIME)',
    '01/02/10000' => 'anno a cinque cifre',
];

foreach ($nonValide as $valore => $motivo) {
    check("data rifiutata, $motivo", fn () =>
        FilterDate::buildCondition('creation', (string) $valore, null) === ''
    );
}

check('date limite accettate (anni 1000 e 9999)', fn () =>
    FilterDate::buildCondition('creation', '01/01/1000', '31/12/9999')
        === "`creation` BETWEEN '1000-01-01 00:00:00' AND '9999-12-31 23:59:59' "
);

check('29 febbraio di un anno bisestile accettato', fn () =>
    FilterDate::buildCondition('creation', '29/02/2028', null)
        === "`creation` >= '2028-02-29 00:00:00' "
);

check('valori non stringa ignorati senza errori', fn () =>
    FilterDate::buildCondition('creation', ['01/02/2026'], 20260215) === ''
);

check('nome colonna passato da Query::escapeIdentifier()', fn () =>
    FilterDate::buildCondition('creation` = 1 OR `x', '01/02/2026', '15/02/2026')
        === "`creation`` = 1 OR ``x` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' "
);

echo "\nFiltro completo (GET, HTML, titolo)\n";

check('GET legittimo: query, titolo e campi come prima', function () {
    $filtro = filtro(['date_from' => '01/02/2026', 'date_to' => '15/02/2026']);

    return $filtro->query === "`creation` BETWEEN '2026-02-01 00:00:00' AND '2026-02-15 23:59:59' "
        && $filtro->title === 'dal 01/02/2026 al 15/02/2026'
        && str_contains($filtro->filter, 'name="date_from" value="01/02/2026"')
        && str_contains($filtro->filter, 'name="date_to" value="15/02/2026"');
});

check('senza GET: ultimi N giorni e query mai vuota', function () {
    $filtro = filtro([]);
    $oggi = new DateTime('now');

    return $filtro->title === 'ultimi 30 giorni'
        && $filtro->query === "`creation` BETWEEN '".(clone $oggi)->modify('-30 days')->format('Y-m-d')." 00:00:00' AND '".$oggi->format('Y-m-d')." 23:59:59' ";
});

check('solo date_from: condizione aperta e titolo "dal ..."', function () {
    $filtro = filtro(['date_from' => '01/02/2026']);

    return $filtro->query === "`creation` >= '2026-02-01 00:00:00' "
        && $filtro->title === 'dal 01/02/2026'
        && str_contains($filtro->filter, 'name="date_to" value=""');
});

check('SQL injection in date_from: resta solo la condizione su date_to', function () {
    $filtro = filtro(['date_from' => "01/01/2026' OR '1'='1", 'date_to' => '31/01/2026']);

    return $filtro->query === "`creation` <= '2026-01-31 23:59:59' "
        && $filtro->title === 'fino al 31/01/2026';
});

check('SQL injection in entrambe le date: si torna agli ultimi N giorni', function () {
    $base = filtro([]);
    $filtro = filtro(['date_from' => "1' OR 1=1 -- /x/y", 'date_to' => "2' OR 1=1 -- /x/y"]);

    return $filtro->query === $base->query && $filtro->title === $base->title;
});

check('XSS in date_from: niente markup nei campi e nel titolo', function () {
    $filtro = filtro(['date_from' => '01/01/2026"><img src=x onerror=alert(1)>', 'date_to' => '31/01/2026']);

    return !str_contains($filtro->filter, '<img')
        && !str_contains($filtro->title, '<')
        && str_contains($filtro->filter, 'name="date_from" value=""');
});

check('array nel GET (date, mese, anno): ignorati senza warning', function () {
    $base = filtro([]);
    $filtro = filtro(['date_from' => ['x'], 'date_to' => ['y'], 'month' => ['9'], 'year' => ['2026']]);

    return $filtro->query === $base->query && $filtro->title === $base->title;
});

check('customGet con markup nei valori: link codificati, valori intatti', function () {
    $redirect = '"><script>alert(1)</script>';
    $id = "5' onmouseover='alert(1)";
    $filtro = filtro([], ['redirect' => $redirect, 'id' => $id]);

    return !str_contains($filtro->filter, '<script>')
        && ogniLink($filtro->filter, fn (array $params) => ($params['redirect'] ?? null) === $redirect && ($params['id'] ?? null) === $id);
});

check('customGet con markup nella chiave: chiave codificata', function () {
    $filtro = filtro([], ['x"><img src=x onerror=alert(1)>' => '1']);

    return !str_contains($filtro->filter, '<img');
});

check('customGet con array (filtri a checkbox): valori conservati nei link', function () {
    $filtro = filtro([], ['stato' => ['attivo', 'sospeso'], 'redirect' => '', 'id' => '']);

    return ogniLink($filtro->filter, fn (array $params) => ($params['stato'] ?? null) === ['attivo', 'sospeso']);
});

check('link dei mesi con mese numerico e & codificato', function () {
    $filtro = filtro([]);

    return str_contains($filtro->filter, 'href="?month='.date('n').'&amp;year='.date('Y').'"');
});

check('mese numerico corrente: filtra il mese intero', function () {
    $filtro = filtro(['month' => date('n'), 'year' => date('Y')]);

    return $filtro->query === "`creation` BETWEEN '".date('Y-m-01')." 00:00:00' AND '".date('Y-m-t')." 23:59:59' "
        && $filtro->title === 'di date.month.'.strtolower(date('F')).' '.date('Y')
        && str_contains($filtro->filter, 'class="btn btn-dark btn-sm col"');
});

check('mese con il nome inglese (vecchio formato): ignorato', function () {
    $base = filtro([]);
    $filtro = filtro(['month' => date('F'), 'year' => date('Y')]);

    return $filtro->query === $base->query && $filtro->title === $base->title;
});

$mesiNonValidi = [
    [date('n').' OR 1=1', date('Y')],
    ['13', date('Y')],
    ['0', date('Y')],
    [' '.date('n'), date('Y')],
    [date('n').'.0', date('Y')],
    [date('n'), '99999'],
    [date('n'), '0999'],
    [date('n'), date('Y').' OR 1=1'],
];

foreach ($mesiNonValidi as [$mese, $anno]) {
    check("mese/anno non validi ignorati: month=".var_export($mese, true).", year=".var_export($anno, true), function () use ($mese, $anno) {
        $base = filtro([]);
        $filtro = filtro(['month' => $mese, 'year' => $anno]);

        return $filtro->query === $base->query && $filtro->title === $base->title;
    });
}

summary();
