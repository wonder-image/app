<?php
/**
 * Test autonomo (niente PHPUnit nel repo):
 *   php tests/Security/FilterCustomLegacyHardeningTest.php
 *
 * filterCustom(), createFilterCustom(), filterLimit(), filterSearch() e
 * createSearchBar() di app/function/backend/filter.php sono i filtri legacy
 * delle liste del backend. Mettevano nell'SQL senza escape i valori dei filtri
 * personalizzati e il limite presi dal GET, e nell'HTML senza escape i
 * parametri della query string (campi nascosti del form), la ricerca (barra e
 * titolo) e il limite (titolo). I valori dei filtri devono stare fra le
 * opzioni del filtro ed entrare nella query con l'escape della connessione,
 * le colonne passano da Query::escapeIdentifier() e il limite deve essere uno
 * di quelli dei bottoni.
 *
 * Le stesse funzioni devono reggere senza warning ne' TypeError le
 * configurazioni incomplete dei siti: filtri senza opzioni o senza type,
 * sorgenti function che non restituiscono un array, sezione e categoria senza
 * sottocategoria. Con limit=all e senza ricerca filterLimit() restituisce la
 * query completa e le righe selezionate.
 *
 * Le opzioni delle sorgenti function tengono le loro chiavi, come quelle delle
 * altre sorgenti: con gli id dei record come chiavi, campi, whitelist e query
 * usano gli id e non le posizioni.
 *
 * Lo script dei filtri a cascata sezione -> categoria -> sottocategoria
 * definisce ogni funzione che usa. Senza il filtro categoria le sottocategorie
 * seguono solo le sezioni, come le categorie, e lo script non chiama
 * filterCategory(); con tutti e tre i filtri resta quello di prima, byte per
 * byte. Lo script cerca i campi con le classi section, category e
 * subcategory: un filtro reso con check() ha la sua classe quando la catena
 * regge, cioe' quando anche la sezione, e la categoria se c'e', escono con
 * check(). Una select non ha campi spuntati: se la sezione e' una select, le
 * categorie con la classe resterebbero nascoste. Il CheckGroup mette la classe
 * nell'attributo class di ogni input, dopo quella del tema, invece di
 * aggiungere un secondo attributo class che il browser ignora.
 *
 * check() e' la funzione di harness.php, quindi qui non si puo' caricare
 * input.php: il form con i campi veri gira in un processo a parte.
 *
 * Le funzioni SQL del core e i campi del form sono stub: sqlSelect() registra
 * le query e restituisce le righe preparate dal test. La connessione $mysqli
 * globale e' finta ed escapa come una connessione con NO_BACKSLASH_ESCAPES:
 * l'apice si raddoppia e addslashes() non protegge piu'. I primi test di ogni
 * gruppo fissano l'SQL per i dati legittimi.
 */
declare(strict_types=1);

use Wonder\App\Support\AttributeString;
use Wonder\Elements\Form\Components\CheckGroup;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
require __DIR__ . '/../../app/function/string/common.php';
require __DIR__ . '/../../app/function/string/sanitize.php';
require __DIR__ . '/../../app/function/backend/filter.php';

// Stub di app/function/sql.php: nessun database, sqlSelect() registra le chiamate.
function sqlSelect(...$args): object
{
    $GLOBALS['__select'][] = $args;

    $righe = $GLOBALS['__righe'][$args[0]] ?? [];

    return (object) ['Nrow' => count($righe), 'row' => $righe, 'exists' => $righe !== []];
}

function sqlCount(...$args): int
{
    return $GLOBALS['__count'];
}

function sqlColumnExists(...$args): bool
{
    return $GLOBALS['__posizione'];
}

// Stub dei campi di app/function/backend/input.php: registrano opzioni e valore ricevuti.
// check() e' gia' la funzione di harness.php, quindi i test del form usano
// solo filtri select, radio (fino a 4 opzioni) e tree.
function select(...$args): string
{
    $GLOBALS['__campi'][] = ['select', ...$args];

    return "<campo {$args[1]}>";
}

function checkTree(...$args): string
{
    $GLOBALS['__campi'][] = ['checkTree', ...$args];

    return "<campo {$args[1]}>";
}

function opzioniColore(): array
{
    return ['rosso' => 'Rosso', 'blu' => 'Blu'];
}

// Sorgente 'function' che non trova opzioni e non restituisce un array.
function opzioniNulle(): ?array
{
    return null;
}

// Sorgente 'function' con gli id dei record come chiavi, come le opzioni dal database.
function opzioniMarche(): array
{
    return [12 => 'Acme', 40 => 'Beta'];
}

// Sorgente 'function' di un filtro tree: chiavi intere, figli in 'child'.
function opzioniReparti(): array
{
    return ALBERO;
}

// Sorgente 'function' che restituisce un elenco: le chiavi sono le posizioni da 0.
function opzioniLista(): array
{
    return ['Rosso', 'Blu'];
}

// Sorgente 'function' con una sua scelta vuota (''), in fondo.
function opzioniConVuota(): array
{
    return ['rosso' => 'Rosso', '' => 'Qualsiasi'];
}

// I valori non validi vanno ignorati in silenzio: un warning o un notice fa fallire il test.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

const STATO = ['name' => 'Stato', 'type' => 'checkbox', 'array' => [1 => 'Bozza', 2 => 'Pubblicato', 3 => 'Archiviato']];
const VISIBILE = ['name' => 'Visibile', 'type' => 'radio'];
const ALBERO = [3 => ['name' => 'Tre', 'child' => [7 => 'Sette']], 4 => 'Quattro'];
const MARCHE = [['id' => 4, 'name' => 'Acme'], ['id' => 9, 'name' => 'Beta']];
const RIGHE_TAG = [
    ['id' => 3, 'tag' => '["a","c"]'],
    ['id' => 4, 'tag' => '["c"]'],
    ['id' => 5, 'tag' => '["b"]'],
    ['id' => 6, 'tag' => ''],
];
const RIGHE_SEZIONI = [
    'section' => [['id' => 1, 'name' => 'Uomo'], ['id' => 2, 'name' => 'Donna']],
    'category' => [['id' => 5, 'name' => 'Scarpe', 'section_id' => '1,2']],
    'subcategory' => [['id' => 8, 'name' => 'Sneakers', 'section_id' => '1', 'category_id' => '5']],
];

/** Connessione con NO_BACKSLASH_ESCAPES: mysqli raddoppia l'apice e \' non lo protegge piu'. */
function connessione(): mysqli
{
    return new class extends mysqli {
        public function __construct()
        {
        }

        public function real_escape_string(string $string): string
        {
            return str_replace("'", "''", $string);
        }
    };
}

/**
 * Prepara una richiesta con questi parametri: $_GET e
 * $_SERVER['QUERY_STRING'] arrivano dalla stessa query string, come in PHP.
 */
function richiesta(array $parametri, array $globali = [], array $righe = []): void
{
    foreach (['FILTER_CUSTOM', 'QUERY_CUSTOM', 'FILTER_ORDER', 'FILTER_DIRECTION', 'FILTER_SEARCH', 'QUERY_ORDER', 'QUERY_DIRECTION'] as $nome) {
        $GLOBALS[$nome] = $globali[$nome] ?? null;
    }

    $GLOBALS['NAME'] = (object) ['table' => 'prova'];
    $GLOBALS['TEXT'] = (object) ['titleP' => 'articoli', 'all' => 'tutti', 'article' => 'gli', 'last' => 'ultimi'];
    $GLOBALS['mysqli'] = connessione();
    $GLOBALS['__count'] = $globali['__count'] ?? 0;
    $GLOBALS['__posizione'] = $globali['__posizione'] ?? false;
    $GLOBALS['__righe'] = $righe;
    $GLOBALS['__select'] = [];
    $GLOBALS['__campi'] = [];

    $_SERVER['QUERY_STRING'] = http_build_query($parametri, '', '&');
    parse_str($_SERVER['QUERY_STRING'], $get);
    $_GET = $get;
}

/** filterCustom() con questi filtri su una richiesta con questi parametri. */
function personalizzati(array $filtri, array $parametri, array $righe = [], array $globali = []): object
{
    richiesta($parametri, ['FILTER_CUSTOM' => $filtri] + $globali, $righe);

    return filterCustom();
}

/** filterLimit() su una richiesta con questi parametri e 120 righe in tabella. */
function limite(array $parametri, array $globali = []): object
{
    richiesta($parametri, $globali + ['__count' => 120]);

    return filterLimit();
}

/** filterSearch() su una richiesta con questi parametri, cercando in name e surname. */
function ricerca(array $parametri, array $globali = []): object
{
    richiesta($parametri, $globali + ['FILTER_SEARCH' => ['name', 'surname']]);

    return filterSearch();
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

/** Lo script di filterCategory(), dalla funzione fino al click sulle sezioni. */
function scriptCategoria(string $html): string
{
    $inizio = strpos($html, 'function filterCategory()');
    $fine = $inizio === false ? false : strpos($html, '$(\'.section\').click', $inizio);

    return ($inizio === false || $fine === false) ? '' : substr($html, $inizio, $fine - $inizio);
}

/** Lo script dei filtri in fondo al form, da <script> a </script>. */
function scriptFiltri(string $html): string
{
    $inizio = strrpos($html, '<script>');
    $fine = $inizio === false ? false : strpos($html, '</script>', $inizio);

    return ($inizio === false || $fine === false) ? '' : substr($html, $inizio, $fine + strlen('</script>') - $inizio);
}

/** createFilterCustom() con i filtri sezione, categoria e sottocategoria indicati, in quest'ordine. */
function formSezioni(array $filtri): object
{
    $nomi = ['section' => 'Sezione', 'category' => 'Categoria', 'subcategory' => 'Sottocategoria'];
    $config = [];

    foreach ($filtri as $filtro) {
        $config[$filtro] = ['name' => $nomi[$filtro], 'type' => 'select'] + ($filtro === 'section' ? ['database' => true] : []);
    }

    richiesta([], ['FILTER_CUSTOM' => $config], RIGHE_SEZIONI);

    return createFilterCustom();
}

/** Gli input delle opzioni (checkbox e radio), nell'ordine del markup. */
function inputOpzioni(string $html): array
{
    preg_match_all('/<input\b[^>]*\btype="(?:checkbox|radio)"[^>]*>/', $html, $matches);

    return $matches[0];
}

/** Il valore dell'attributo class di un input; un input senza class o con due fa fallire il test. */
function classeInput(string $input): string
{
    if (preg_match_all('/\sclass="([^"]*)"/', $input, $matches) !== 1) {
        throw new RuntimeException('attributi class: '.count($matches[1]).' in '.$input);
    }

    return $matches[1][0];
}

/** name=value => class di ogni input delle opzioni. */
function classiOpzioni(string $html): array
{
    $classi = [];

    foreach (inputOpzioni($html) as $input) {
        preg_match('/\sname="([^"]*)"/', $input, $nome);
        preg_match('/\svalue="([^"]*)"/', $input, $valore);
        $classi[($nome[1] ?? '').'='.($valore[1] ?? '')] = classeInput($input);
    }

    return $classi;
}

/** Le categorie come CheckGroup: 5 sta nelle sezioni 1 e 2 e ha un figlio, 6 non ha filtri. */
function gruppoCategorie(string $tipo = 'checkbox'): CheckGroup
{
    return (new CheckGroup('category'))
        ->label('Categoria')
        ->options([5 => ['name' => 'Scarpe', 'filter' => ['section' => '["1","2"]'], 'child' => [7 => 'Stivali']], 6 => 'Borse'])
        ->inputType($tipo);
}

/** Il tema Wonder da' a ogni opzione un id casuale. */
function senzaIdCasuali(array $input): array
{
    return preg_replace('/id="checkbox_[a-z]+"/', 'id="checkbox_ID"', $input);
}

/** filterCascadeClasses() con questi filtri (type o config intera); senza opzioni indicate, un filtro ne ha 2. */
function classiCascata(array $filtri, array $opzioni = []): array
{
    $config = [];
    $elenchi = [];

    foreach ($filtri as $tabella => $filtro) {
        $config[$tabella] = is_array($filtro) ? $filtro : ['type' => $filtro];
        $elenchi[$tabella] = $opzioni[$tabella] ?? [1 => 'Uno', 2 => 'Due'];
    }

    return filterCascadeClasses($config, $elenchi);
}

/**
 * Il form di createFilterCustom() con i campi veri di input.php, in un processo
 * a parte: qui check() e' la funzione di harness.php. Le righe sono quelle di
 * RIGHE_SEZIONI, i campi escono col tema bootstrap.
 */
function formVero(array $filtri, array $parametri = []): string
{
    static $form = [];

    $dati = json_encode(['filtri' => $filtri, 'parametri' => $parametri, 'righe' => RIGHE_SEZIONI]);

    if (isset($form[$dati])) {
        return $form[$dati];
    }

    $codice = <<<'PHP'
        require 'vendor/autoload.php';
        require 'app/function/string/common.php';
        require 'app/function/string/sanitize.php';
        require 'app/function/backend/input.php';
        require 'app/function/backend/filter.php';

        function sqlSelect(...$args) {
            $righe = $GLOBALS['__righe'][$args[0]] ?? [];

            return (object) ['Nrow' => count($righe), 'row' => $righe, 'exists' => $righe !== []];
        }

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        $dati = json_decode($argv[1], true);
        $GLOBALS['__righe'] = $dati['righe'];
        $FILTER_CUSTOM = $dati['filtri'];
        $_SERVER['QUERY_STRING'] = http_build_query($dati['parametri'], '', '&');
        parse_str($_SERVER['QUERY_STRING'], $_GET);
        \Wonder\App\Theme::set('bootstrap');

        echo json_encode(['html' => createFilterCustom()->html]);
        PHP;

    $uscita = (string) shell_exec(
        'cd '.escapeshellarg(dirname(__DIR__, 2))
        .' && '.escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($codice).' -- '.escapeshellarg($dati).' 2>&1'
    );
    $risultato = json_decode($uscita, true);

    if (!is_array($risultato) || !is_string($risultato['html'] ?? null)) {
        throw new RuntimeException('processo del form: '.trim($uscita));
    }

    return $form[$dati] = $risultato['html'];
}

echo "Filtri personalizzati: dati legittimi\n";

check('checkbox: SQL identico a prima, byte per byte', function () {
    $filtro = personalizzati(['stato' => STATO], ['stato' => ['', '1', '2']]);

    return $filtro->query_all === "`deleted` = 'false' "
        && $filtro->query_filter === "`stato` IN ('1', '2') "
        && $filtro->query_order === "ORDER BY `creation` DESC "
        && $filtro->query_order_col === 'creation'
        && $filtro->query_order_dir === 'DESC'
        && $filtro->arrow === false
        && $filtro->title === 'Tutti gli articoli';
});

check('radio visible: SQL come prima', function () {
    $filtro = personalizzati(['visible' => VISIBILE], ['visible' => 'true']);

    return $filtro->query_filter === "`visible` = 'true' ";
});

check('colonna diversa dalla chiave del filtro (column): SQL come prima', function () {
    $filtro = personalizzati(['categoria' => ['type' => 'select', 'column' => 'category_id', 'array' => [7 => 'Sette', 8 => 'Otto']]], ['categoria' => '7']);

    return $filtro->query_filter === "`category_id` = '7' ";
});

check('due filtri e $QUERY_CUSTOM: uniti con AND, come prima', function () {
    $filtro = personalizzati(['visible' => VISIBILE, 'stato' => STATO], ['visible' => 'false', 'stato' => ['', '3']], [], ['QUERY_CUSTOM' => "`type` = 'news'"]);

    return $filtro->query_all === "`type` = 'news' AND `deleted` = 'false' "
        && $filtro->query_filter === "`visible` = 'false' AND `stato` IN ('3') ";
});

check('albero: valori dei figli accettati, SQL come prima', function () {
    $filtro = personalizzati(['albero' => ['type' => 'tree', 'array' => ALBERO]], ['albero' => ['', '3', '7']]);

    return $filtro->query_filter === "`albero` IN ('', '3', '7') ";
});

check('opzioni dal database: SQL come prima', function () {
    $filtro = personalizzati(['marca' => ['type' => 'checkbox', 'database' => true]], ['marca' => ['', '9']], ['marca' => MARCHE]);

    return $filtro->query_filter === "`marca` IN ('9') ";
});

check('opzioni da una funzione: SQL come prima', function () {
    $filtro = personalizzati(['colore' => ['type' => 'radio', 'function' => 'opzioniColore']], ['colore' => 'blu']);

    return $filtro->query_filter === "`colore` = 'blu' ";
});

check('categorie filtrate per sezione: SQL come prima', function () {
    $filtro = personalizzati(
        ['section' => ['type' => 'checkbox', 'database' => true], 'category' => ['type' => 'checkbox']],
        ['category' => ['', '5']],
        ['category' => [['id' => 5, 'name' => 'Scarpe', 'section_id' => '1,2']]]
    );

    return $filtro->query_filter === "`category` IN ('5') ";
});

check('colonna multipla (checkbox): id delle righe trovate, come prima', function () {
    $filtro = personalizzati(
        ['tag' => ['type' => 'checkbox', 'column_type' => 'multiple', 'array' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]],
        ['tag' => ['', 'a', 'b']],
        ['prova' => RIGHE_TAG]
    );

    return $filtro->query_filter === "id IN (3 ,5) "
        && $GLOBALS['__select'] === [['prova', "`deleted` = 'false' "]];
});

check('colonna multipla (radio): id delle righe trovate, come prima', function () {
    $filtro = personalizzati(
        ['tag' => ['type' => 'radio', 'column_type' => 'multiple', 'array' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]],
        ['tag' => 'c'],
        ['prova' => RIGHE_TAG]
    );

    return $filtro->query_filter === "id IN (3 ,4) ";
});

check('senza filtri nel GET, o col solo campo vuoto dei checkbox: nessuna condizione', function () {
    $vuoto = personalizzati(['stato' => STATO, 'visible' => VISIBILE], []);
    $soloCampoVuoto = personalizzati(['stato' => STATO, 'visible' => VISIBILE], ['stato' => [''], 'visible' => '']);

    return $vuoto->query_filter === ''
        && $soloCampoVuoto->query_filter === ''
        && $soloCampoVuoto->query_order === "ORDER BY `creation` DESC ";
});

check('filter(): query completa e righe contate, come prima', function () {
    richiesta(['stato' => ['', '1']], ['FILTER_CUSTOM' => ['stato' => STATO], '__count' => 120]);
    $filtro = filter();

    return $filtro->query === "`deleted` = 'false' AND `stato` IN ('1') ORDER BY `creation` DESC "
        && $filtro->lines === 120
        && $filtro->title === 'Lista articoli';
});

echo "\nOrdinamento\n";

check('colonna position senza filtri: ordine per position, come prima', function () {
    $filtro = personalizzati(['stato' => STATO], [], [], ['__posizione' => true]);

    return $filtro->query_order === "ORDER BY `position` ASC "
        && $filtro->query_order_col === 'position'
        && $filtro->query_order_dir === 'ASC'
        && $filtro->arrow === true;
});

check('$FILTER_ORDER e $FILTER_DIRECTION del sito: come prima', function () {
    $discendente = personalizzati([], [], [], ['FILTER_ORDER' => 'name', 'FILTER_DIRECTION' => 'desc']);
    $senzaDirezione = personalizzati([], [], [], ['FILTER_ORDER' => 'name']);

    return $discendente->query_order === "ORDER BY `name` desc "
        && $discendente->query_order_col === 'name'
        && $discendente->query_order_dir === 'desc'
        && $senzaDirezione->query_order === "ORDER BY `name` ASC ";
});

check('$FILTER_ORDER passato da Query::escapeIdentifier()', function () {
    $filtro = personalizzati([], [], [], ['FILTER_ORDER' => 'name` DESC, (SELECT 1) -- ']);

    return $filtro->query_order === "ORDER BY `name`` DESC, (SELECT 1) -- ` ASC ";
});

check('$FILTER_DIRECTION diversa da ASC e DESC: ASC', function () {
    $filtro = personalizzati([], [], [], ['FILTER_ORDER' => 'name', 'FILTER_DIRECTION' => 'DESC, (SELECT 1)']);

    return $filtro->query_order === "ORDER BY `name` ASC "
        && $filtro->query_order_dir === 'ASC';
});

echo "\nFiltri personalizzati: SQL injection\n";

check('checkbox: valore fuori dalle opzioni scartato', function () {
    $filtro = personalizzati(['stato' => STATO], ['stato' => ['', '1', "1') OR ('1'='1"]]);

    return $filtro->query_filter === "`stato` IN ('1') ";
});

check('radio: valore fuori dalle opzioni scartato, nessuna condizione', function () {
    $filtro = personalizzati(['visible' => VISIBILE], ['visible' => "true' OR '1'='1"]);

    return $filtro->query_filter === '';
});

check('albero: valore fuori dalle opzioni (figli compresi) scartato', function () {
    $filtro = personalizzati(['albero' => ['type' => 'tree', 'array' => ALBERO]], ['albero' => ['', '7', "7') OR 1=1 -- "]]);

    return $filtro->query_filter === "`albero` IN ('', '7') ";
});

check('opzioni dal database: id fuori dalle righe scartato', function () {
    $filtro = personalizzati(['marca' => ['type' => 'checkbox', 'database' => true]], ['marca' => ['', '9', '9) OR (1=1']], ['marca' => MARCHE]);

    return $filtro->query_filter === "`marca` IN ('9') ";
});

check('opzioni da una funzione: valore fuori dalle opzioni scartato', function () {
    $filtro = personalizzati(['colore' => ['type' => 'radio', 'function' => 'opzioniColore']], ['colore' => "blu' OR '1'='1"]);

    return $filtro->query_filter === '';
});

check('categorie filtrate per sezione: id fuori dalle categorie scartato', function () {
    $filtro = personalizzati(
        ['section' => ['type' => 'checkbox', 'database' => true], 'category' => ['type' => 'checkbox']],
        ['category' => ['', '5', '5) OR (1=1']],
        ['category' => [['id' => 5, 'name' => 'Scarpe', 'section_id' => '1,2']]]
    );

    return $filtro->query_filter === "`category` IN ('5') ";
});

check('opzioni sconosciute: valori escapati dalla connessione', function () {
    $filtro = personalizzati(
        ['codice' => ['type' => 'select'], 'codici' => ['type' => 'checkbox']],
        ['codice' => "a' OR '1'='1", 'codici' => ['', "x') OR 1=1 -- "]]
    );

    return $filtro->query_filter === "`codice` = 'a'' OR ''1''=''1' AND `codici` IN ('x'') OR 1=1 -- ') ";
});

check('opzione con l\'apice: valore escapato anche se ammesso', function () {
    $filtro = personalizzati(['citta' => ['type' => 'radio', 'array' => ["l'aquila" => "L'Aquila"]]], ['citta' => "l'aquila"]);

    return $filtro->query_filter === "`citta` = 'l''aquila' ";
});

check('nome colonna passato da Query::escapeIdentifier()', function () {
    $filtro = personalizzati(
        ['stato' => ['column' => 'stato` = 1 OR `x'] + STATO, 'visible' => ['column' => 'visible` = 1 OR `y'] + VISIBILE],
        ['stato' => ['', '1'], 'visible' => 'true']
    );

    return $filtro->query_filter === "`stato`` = 1 OR ``x` IN ('1') AND `visible`` = 1 OR ``y` = 'true' ";
});

echo "\nFiltri personalizzati: valori non validi (ignorati senza warning)\n";

check('checkbox con un valore singolo al posto dell\'array: ignorato', function () {
    $filtro = personalizzati(['stato' => STATO], ['stato' => "1' OR '1'='1"]);

    return $filtro->query_filter === '';
});

check('radio con un array: ignorato', function () {
    $filtro = personalizzati(['visible' => VISIBILE], ['visible' => ['true']]);

    return $filtro->query_filter === '';
});

check('checkbox con array annidati: restano solo i valori semplici ammessi', function () {
    $filtro = personalizzati(['stato' => STATO], ['stato' => ['', ['1'], '2']]);

    return $filtro->query_filter === "`stato` IN ('2') ";
});

check('colonna multipla con un valore singolo al posto dell\'array: ignorato', function () {
    $filtro = personalizzati(
        ['tag' => ['type' => 'checkbox', 'column_type' => 'multiple', 'array' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]],
        ['tag' => 'a'],
        ['prova' => RIGHE_TAG]
    );

    return $filtro->query_filter === '';
});

echo "\nForm dei filtri (createFilterCustom)\n";

check('campi nascosti: gli altri parametri, senza i filtri, come prima', function () {
    richiesta(
        ['q' => 'rossi', 'visible' => 'true', 'albero' => ['', '3']],
        ['FILTER_CUSTOM' => ['visible' => VISIBILE, 'albero' => ['name' => 'Albero', 'type' => 'tree', 'array' => ALBERO]]]
    );
    $form = createFilterCustom();

    return str_contains($form->html, "<input type='hidden' name='q' value='rossi'>")
        && campiNascosti($form->html) === ['q' => 'rossi'];
});

check('opzioni e valore passati ai campi come prima', function () {
    richiesta(
        ['visible' => 'true', 'marca' => '9', 'colore' => 'blu', 'categoria' => '7', 'albero' => ['', '3']],
        ['FILTER_CUSTOM' => [
            'visible' => VISIBILE,
            'marca' => ['name' => 'Marca', 'type' => 'radio', 'database' => true],
            'colore' => ['name' => 'Colore', 'type' => 'radio', 'function' => 'opzioniColore'],
            'categoria' => ['name' => 'Categoria', 'type' => 'select', 'column' => 'category_id', 'array' => [7 => 'Sette', 8 => 'Otto']],
            'albero' => ['name' => 'Albero', 'type' => 'tree', 'array' => ALBERO],
        ]],
        ['marca' => MARCHE]
    );
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Visibile', 'visible', ['' => 'Tutti', 'true' => 'Visibile', 'false' => 'Nascosto'], 'old', null, 'true'],
        ['select', 'Marca', 'marca', ['' => 'Tutti', 4 => 'Acme', 9 => 'Beta'], 'old', null, '9'],
        ['select', 'Colore', 'colore', ['' => 'Tutti', 'rosso' => 'Rosso', 'blu' => 'Blu'], 'old', null, 'blu'],
        ['select', 'Categoria', 'categoria', [7 => 'Sette', 8 => 'Otto'], 'old', null, '7'],
        ['checkTree', 'Albero', 'albero', ALBERO, null, 'checkbox', true, ['', '3']],
    ]
        && $GLOBALS['__select'] === [['marca', ['deleted' => 'false'], null, 'name', 'ASC']];
});

check('sezioni, categorie e sottocategorie: opzioni e script come prima', function () {
    richiesta([], ['FILTER_CUSTOM' => [
        'section' => ['name' => 'Sezione', 'type' => 'select', 'database' => true],
        'category' => ['name' => 'Categoria', 'type' => 'select'],
        'subcategory' => ['name' => 'Sottocategoria', 'type' => 'select'],
    ]], [
        'section' => [['id' => 1, 'name' => 'Uomo'], ['id' => 2, 'name' => 'Donna']],
        'category' => [['id' => 5, 'name' => 'Scarpe', 'section_id' => '1,2'], ['id' => 6, 'name' => 'Borse', 'section_id' => null]],
        'subcategory' => [['id' => 8, 'name' => 'Sneakers', 'section_id' => '1', 'category_id' => '5']],
    ]);
    $form = createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Sezione', 'section', [1 => 'Uomo', 2 => 'Donna'], 'old', null, ''],
        ['select', 'Categoria', 'category', [
            5 => ['name' => 'Scarpe', 'filter' => ['section' => '["1","2"]']],
            6 => ['name' => 'Borse', 'filter' => ['section' => '[]']],
        ], 'old', null, ''],
        ['select', 'Sottocategoria', 'subcategory', [
            8 => ['name' => 'Sneakers', 'filter' => ['section' => '["1"]', 'category' => '["5"]']],
        ], 'old', null, ''],
    ]
        && substr_count($form->html, 'filterSubcategory();') === 2
        && str_contains($form->html, 'function filterCategory()')
        && str_contains($form->html, 'function disabledCheckbox(element)');
});

check('markup nei valori della query string: campi nascosti codificati, valori intatti', function () {
    $redirect = "x'><script>alert(1)</script>";
    $id = "5' onmouseover='alert(1)";
    richiesta(['redirect' => $redirect, 'id' => $id], ['FILTER_CUSTOM' => ['visible' => VISIBILE]]);
    $form = createFilterCustom();

    return !str_contains($form->html, '<script>alert(1)</script>')
        && campiNascosti($form->html) === ['redirect' => $redirect, 'id' => $id];
});

check('markup nella chiave di un parametro: chiave codificata e conservata', function () {
    $chiave = "x'><svg/onload=alert(1)>";
    richiesta([$chiave => '1'], ['FILTER_CUSTOM' => ['visible' => VISIBILE]]);
    $form = createFilterCustom();

    return !str_contains($form->html, '<svg')
        && campiNascosti($form->html) === [$chiave => '1'];
});

check('parametri a piu\' valori: conservati nei campi nascosti, senza warning', function () {
    $parametri = ['ids' => ['1', '2'], 'f' => ['tipo' => 'news']];
    richiesta($parametri, ['FILTER_CUSTOM' => ['visible' => VISIBILE]]);
    $form = createFilterCustom();

    return campiNascosti($form->html) === $parametri;
});

echo "\nfilterLimit()\n";

check('limit=50: SQL identico a prima, byte per byte', function () {
    $limite = limite(['limit' => '50']);

    return $limite->query === "`deleted` = 'false' ORDER BY `creation` DESC LIMIT 50"
        && $limite->title === 'Ultimi 50 articoli'
        && $limite->selected_lines === '50'
        && $limite->lines === 120
        && $limite->arrow === false
        && str_contains($limite->html, "<a href='?limit=50' class='btn btn-dark btn-sm col'")
        && str_contains($limite->html, "<a href='?limit=25' class='btn btn-outline-dark btn-sm col'");
});

check('senza limit: gli ultimi 25, come prima', function () {
    $limite = limite([]);

    return $limite->query === "`deleted` = 'false' ORDER BY `creation` DESC LIMIT 25"
        && $limite->title === 'Ultimi 25 articoli'
        && $limite->selected_lines === 25
        && str_contains($limite->html, "<a href='?limit=25' class='btn btn-dark btn-sm col'");
});

check('ordinamento e $QUERY_CUSTOM del sito: come prima', function () {
    $limite = limite(['limit' => '100'], ['QUERY_CUSTOM' => "`type` = 'news'", 'QUERY_ORDER' => '`name`', 'QUERY_DIRECTION' => 'DESC']);

    return $limite->query === "`type` = 'news' AND `deleted` = 'false' ORDER BY `name` DESC LIMIT 100";
});

check('limit=all con una ricerca: la query della ricerca, come prima', function () {
    $limite = limite(['limit' => 'all', 'q' => 'rossi'], ['FILTER_SEARCH' => ['name']]);

    return $limite->query === "`deleted` = 'false' AND CONCAT_WS(' ',`name`) LIKE '%rossi%'ORDER BY `creation` DESC "
        && $limite->title === 'Articoli inerenti alla tua ricerca: rossi'
        && str_contains($limite->html, "<a href='?limit=all' class='btn btn-dark btn-sm col'");
});

foreach (['50 UNION SELECT password FROM user', '<script>alert(1)</script>', '10', '25.0'] as $valore) {
    check('limit fuori dai bottoni: gli ultimi 25, limit='.var_export($valore, true), function () use ($valore) {
        $limite = limite(['limit' => $valore]);

        return $limite->query === "`deleted` = 'false' ORDER BY `creation` DESC LIMIT 25"
            && $limite->title === 'Ultimi 25 articoli';
    });
}

check('limit come array: gli ultimi 25, senza warning', function () {
    $limite = limite(['limit' => ['50']]);

    return $limite->query === "`deleted` = 'false' ORDER BY `creation` DESC LIMIT 25"
        && $limite->title === 'Ultimi 25 articoli';
});

echo "\nRicerca (filterSearch() e createSearchBar())\n";

check('due parole: SQL identico a prima, byte per byte', function () {
    $ricerca = ricerca(['q' => 'mario rossi']);

    return $ricerca->query === "`deleted` = 'false' AND CONCAT_WS(' ',`name`, `surname`) LIKE '%mario%' AND CONCAT_WS(' ',`name`, `surname`) LIKE '%rossi%'ORDER BY `creation` DESC "
        && $GLOBALS['__select'] === [['prova', $ricerca->query]]
        && $ricerca->selected_lines === 0
        && $ricerca->arrow === false
        && $ricerca->title === 'Articoli inerenti alla tua ricerca: mario rossi';
});

check('ordinamento del sito e $QUERY_CUSTOM: come prima', function () {
    $ricerca = ricerca(['q' => 'rossi'], ['QUERY_CUSTOM' => "`type` = 'news'", 'FILTER_ORDER' => 'surname', 'FILTER_DIRECTION' => 'DESC']);

    return $ricerca->query === "`type` = 'news' AND `deleted` = 'false' AND CONCAT_WS(' ',`name`, `surname`) LIKE '%rossi%'ORDER BY `surname` DESC ";
});

check('senza ricerca: nessuna condizione, come prima', function () {
    $ricerca = ricerca([]);

    return $ricerca->query === "`deleted` = 'false' ORDER BY `creation` DESC "
        && $ricerca->arrow === true
        && $ricerca->title === 'Articoli inerenti alla tua ricerca: ';
});

check('barra di ricerca: valore come prima', function () {
    richiesta(['q' => 'rossi']);

    return str_contains(createSearchBar(), "name='q' value='rossi'");
});

check('apice nella ricerca: escape della connessione, non addslashes()', function () {
    $ricerca = ricerca(['q' => "x'OR/**/1=1#"]);

    return $ricerca->query === "`deleted` = 'false' AND CONCAT_WS(' ',`name`, `surname`) LIKE '%x''OR/**/1=1#%'ORDER BY `creation` DESC ";
});

check('markup nella ricerca: titolo codificato', function () {
    $ricerca = ricerca(['q' => '<script>alert(1)</script>']);

    return $ricerca->title === 'Articoli inerenti alla tua ricerca: &lt;script&gt;alert(1)&lt;/script&gt;';
});

check('apice nel titolo: codificato, senza la barra di addslashes()', function () {
    $ricerca = ricerca(['q' => "l'aquila"]);

    return $ricerca->title === 'Articoli inerenti alla tua ricerca: l&#039;aquila';
});

check('ricerca come array: ignorata', function () {
    $ricerca = ricerca(['q' => ['x']]);

    return $ricerca->query === "`deleted` = 'false' ORDER BY `creation` DESC "
        && $ricerca->title === 'Articoli inerenti alla tua ricerca: ';
});

check('barra di ricerca: l\'apice non chiude l\'attributo', function () {
    richiesta(['q' => "x' autofocus onfocus='alert(1)"]);

    return str_contains(createSearchBar(), "name='q' value='x&#039; autofocus onfocus=&#039;alert(1)'");
});

check('barra di ricerca con un array: vuota', function () {
    richiesta(['q' => ['x']]);

    return str_contains(createSearchBar(), "name='q' value=''");
});

check('colonne di $FILTER_SEARCH passate da Query::escapeIdentifier()', function () {
    $ricerca = ricerca(['q' => 'rossi'], ['FILTER_SEARCH' => ['name`) OR 1=1 -- ']]);

    return $ricerca->query === "`deleted` = 'false' AND CONCAT_WS(' ',`name``) OR 1=1 -- `) LIKE '%rossi%'ORDER BY `creation` DESC ";
});

check('ordinamento della ricerca: colonna escapata, direzione solo ASC o DESC', function () {
    $ricerca = ricerca([], ['FILTER_ORDER' => 'name` DESC, (SELECT 1) -- ', 'FILTER_DIRECTION' => 'DESC, (SELECT 1)']);

    return $ricerca->query === "`deleted` = 'false' ORDER BY `name`` DESC, (SELECT 1) -- ` ASC ";
});

echo "\nConfigurazioni incomplete e limit=all (senza warning ne' TypeError)\n";

check('limit=all senza ricerca: query completa e righe dei filtri, titolo "Tutti"', function () {
    $limite = limite(['limit' => 'all', 'stato' => ['', '2']], ['FILTER_CUSTOM' => ['stato' => STATO], 'QUERY_CUSTOM' => "`type` = 'news'"]);

    return $limite->query === "`type` = 'news' AND `deleted` = 'false' AND `stato` IN ('2') ORDER BY `creation` DESC "
        && $limite->selected_lines === 120
        && $limite->lines === 120
        && $limite->arrow === false
        && $limite->title === 'Tutti gli articoli'
        && str_contains($limite->html, "<a href='?limit=all' class='btn btn-dark btn-sm col'");
});

check('limit=all senza filtri, con la colonna position: ordine per position e frecce', function () {
    $limite = limite(['limit' => 'all'], ['__posizione' => true]);

    return $limite->query === "`deleted` = 'false' ORDER BY `position` ASC "
        && $limite->arrow === true
        && $limite->title === 'Tutti gli articoli';
});

check('filtri senza opzioni (select, radio, tree): campi vuoti invece del TypeError', function () {
    richiesta(['codice' => 'AB-12'], ['FILTER_CUSTOM' => [
        'codice' => ['name' => 'Codice', 'type' => 'select', 'column' => 'codice_articolo'],
        'tipo' => ['name' => 'Tipo', 'type' => 'radio'],
        'albero' => ['name' => 'Albero', 'type' => 'tree'],
    ]]);
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Codice', 'codice', [], 'old', null, 'AB-12'],
        ['select', 'Tipo', 'tipo', [], 'old', null, ''],
        ['checkTree', 'Albero', 'albero', [], null, 'checkbox', true, ''],
    ];
});

check('sezione e categoria senza sottocategoria: script della categoria senza filterSubcategory()', function () {
    $filtri = [
        'section' => ['name' => 'Sezione', 'type' => 'select', 'database' => true],
        'category' => ['name' => 'Categoria', 'type' => 'select'],
    ];
    $righe = [
        'section' => [['id' => 1, 'name' => 'Uomo']],
        'category' => [['id' => 5, 'name' => 'Scarpe', 'section_id' => '1']],
    ];

    richiesta([], ['FILTER_CUSTOM' => $filtri + ['subcategory' => ['name' => 'Sottocategoria', 'type' => 'select']]], $righe);
    $conSottocategoria = scriptCategoria(createFilterCustom()->html);
    richiesta([], ['FILTER_CUSTOM' => $filtri], $righe);
    $html = createFilterCustom()->html;

    return $conSottocategoria !== ''
        && !str_contains($html, 'filterSubcategory')
        && scriptCategoria($html) === str_replace('filterSubcategory();', '', $conSottocategoria);
});

check('sorgente function che non restituisce un array: nessuna opzione, valore scartato', function () {
    $filtro = personalizzati(['colore' => ['type' => 'radio', 'function' => 'opzioniNulle']], ['colore' => 'blu']);

    return $filtro->query_filter === '';
});

check('sorgente function che non restituisce un array: campi senza opzioni ("Tutti" per i radio)', function () {
    richiesta([], ['FILTER_CUSTOM' => [
        'colore' => ['name' => 'Colore', 'type' => 'radio', 'function' => 'opzioniNulle'],
        'taglia' => ['name' => 'Taglia', 'type' => 'select', 'function' => 'opzioniNulle'],
    ]]);
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Colore', 'colore', ['' => 'Tutti'], 'old', null, ''],
        ['select', 'Taglia', 'taglia', [], 'old', null, ''],
    ];
});

check('filtro senza type: valore singolo come prima', function () {
    $filtro = personalizzati(
        ['stato' => ['array' => [1 => 'Bozza', 2 => 'Pubblicato']], 'codice' => ['column' => 'codice_articolo']],
        ['stato' => '2', 'codice' => "AB'12"]
    );

    return $filtro->query_filter === "`stato` = '2' AND `codice_articolo` = 'AB''12' ";
});

check('filtro senza type nel form: nessun campo', function () {
    richiesta([], ['FILTER_CUSTOM' => ['codice' => ['name' => 'Codice', 'column' => 'codice_articolo'], 'visible' => VISIBILE]]);
    $form = createFilterCustom();

    return $GLOBALS['__campi'] === [['select', 'Visibile', 'visible', ['' => 'Tutti', 'true' => 'Visibile', 'false' => 'Nascosto'], 'old', null, '']]
        && substr_count($form->html, '<campo ') === 1;
});

check('filtro senza type dopo un altro: il campo precedente non si ripete', function () {
    richiesta([], ['FILTER_CUSTOM' => ['visible' => VISIBILE, 'codice' => ['name' => 'Codice', 'column' => 'codice_articolo']]]);
    $form = createFilterCustom();

    return substr_count($form->html, '<campo visible>') === 1;
});

echo "\nSorgenti function: chiavi delle opzioni\n";

check("sorgente function con chiavi intere: filtra sull'id", function () {
    $filtro = personalizzati(['marca' => ['type' => 'select', 'function' => 'opzioniMarche']], ['marca' => '12']);

    return $filtro->query_filter === "`marca` = '12' ";
});

check('sorgente function con chiavi intere: checkbox sugli id', function () {
    $filtro = personalizzati(['marca' => ['type' => 'checkbox', 'function' => 'opzioniMarche']], ['marca' => ['', '40']]);

    return $filtro->query_filter === "`marca` IN ('40') ";
});

check('sorgente function con chiavi intere: tree sugli id, figli compresi', function () {
    $filtro = personalizzati(['reparto' => ['type' => 'tree', 'function' => 'opzioniReparti']], ['reparto' => ['', '3', '7']]);

    return $filtro->query_filter === "`reparto` IN ('', '3', '7') ";
});

check("sorgente function con chiavi intere: la posizione di un'opzione non e' un valore", function () {
    $filtro = personalizzati(['marca' => ['type' => 'select', 'function' => 'opzioniMarche']], ['marca' => '1']);

    return $filtro->query_filter === '';
});

check('sorgente function con chiavi intere: i campi ricevono gli id', function () {
    richiesta(['marca' => '12'], ['FILTER_CUSTOM' => [
        'marca' => ['name' => 'Marca', 'type' => 'select', 'function' => 'opzioniMarche'],
        'produttore' => ['name' => 'Produttore', 'type' => 'radio', 'function' => 'opzioniMarche'],
        'reparto' => ['name' => 'Reparto', 'type' => 'tree', 'function' => 'opzioniReparti'],
    ]]);
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Marca', 'marca', [12 => 'Acme', 40 => 'Beta'], 'old', null, '12'],
        ['select', 'Produttore', 'produttore', ['' => 'Tutti', 12 => 'Acme', 40 => 'Beta'], 'old', null, ''],
        ['checkTree', 'Reparto', 'reparto', ALBERO, null, 'checkbox', true, ''],
    ];
});

check('sorgente function con un elenco: campi con le chiavi da 0 come prima', function () {
    richiesta([], ['FILTER_CUSTOM' => [
        'colore' => ['name' => 'Colore', 'type' => 'radio', 'function' => 'opzioniLista'],
        'tinta' => ['name' => 'Tinta', 'type' => 'select', 'function' => 'opzioniLista'],
    ]]);
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Colore', 'colore', ['' => 'Tutti', 0 => 'Rosso', 1 => 'Blu'], 'old', null, ''],
        ['select', 'Tinta', 'tinta', [0 => 'Rosso', 1 => 'Blu'], 'old', null, ''],
    ];
});

check('sorgente function con un elenco: SQL come prima', function () {
    $filtro = personalizzati(['colore' => ['type' => 'radio', 'function' => 'opzioniLista']], ['colore' => '1']);

    return $filtro->query_filter === "`colore` = '1' ";
});

check("sorgente function con la chiave '': la sua etichetta al posto di \"Tutti\", in testa nei radio", function () {
    richiesta([], ['FILTER_CUSTOM' => [
        'colore' => ['name' => 'Colore', 'type' => 'radio', 'function' => 'opzioniConVuota'],
        'tinta' => ['name' => 'Tinta', 'type' => 'select', 'function' => 'opzioniConVuota'],
    ]]);
    createFilterCustom();

    return $GLOBALS['__campi'] === [
        ['select', 'Colore', 'colore', ['' => 'Qualsiasi', 'rosso' => 'Rosso'], 'old', null, ''],
        ['select', 'Tinta', 'tinta', ['rosso' => 'Rosso', '' => 'Qualsiasi'], 'old', null, ''],
    ];
});

echo "\nScript dei filtri sezione, categoria e sottocategoria\n";

check('sezione, categoria e sottocategoria: script identico a prima, byte per byte', function () {
    return scriptFiltri(formSezioni(['section', 'category', 'subcategory'])->html) === "<script>\n"
        . "                function disabledCheckbox(element) {\n"
        . "                    element.disabled = true;\n"
        . "                    element.classList.remove('bg-danger');\n"
        . "                    element.classList.remove('border-danger');\n"
        . "                    element.setAttribute('onclick', '');\n"
        . "                    element.parentElement.style.display= 'none';\n"
        . "                }\n"
        . "\n"
        . "                function filterCategory() {\n"
        . "                    document.querySelectorAll('.category').forEach(element => {\n"
        . "                        \n"
        . "                        var section = JSON.parse(element.dataset.section);\n"
        . "                        var sectionFilter = []\n"
        . "                        var checkboxes = document.querySelectorAll('.section:checked');\n"
        . "        \n"
        . "                        for (var i = 0; i < checkboxes.length; i++) {\n"
        . "                            sectionFilter.push(checkboxes[i].value)\n"
        . "                        }\n"
        . "        \n"
        . "                        if (section.some(r=> sectionFilter.includes(r))) {\n"
        . "                            var showSection = true;\n"
        . "                        }else{\n"
        . "                            var showSection = false;\n"
        . "                        }\n"
        . "        \n"
        . "                        if (showSection) {\n"
        . "                            if (element.checked) {\n"
        . "                                element.classList.remove('bg-danger');\n"
        . "                                element.classList.remove('border-danger');\n"
        . "                                element.setAttribute('onclick', '');\n"
        . "                            }\n"
        . "                            element.parentElement.style.display = 'block';\n"
        . "                            element.disabled = false;\n"
        . "                        }else{\n"
        . "                            if (element.checked) {\n"
        . "                                element.disabled = false;\n"
        . "                                element.classList.add('bg-danger');\n"
        . "                                element.classList.add('border-danger');\n"
        . "                                element.setAttribute('onclick', \"disabledCheckbox(this)\");\n"
        . "                                element.parentElement.style.display = 'block';\n"
        . "                            } else {\n"
        . "                                element.disabled = true;\n"
        . "                                element.parentElement.style.display = 'none';\n"
        . "                            }\n"
        . "                        }\n"
        . "        \n"
        . "                        filterSubcategory();\n"
        . "        \n"
        . "                    });\n"
        . "                }\n"
        . "                \n"
        . "                filterCategory();\n"
        . "                \$('.section').click(function(){\n"
        . "                    filterCategory();\n"
        . "                });\n"
        . "                function filterSubcategory() {\n"
        . "                    document.querySelectorAll('.subcategory').forEach(element => {\n"
        . "                        \n"
        . "                        var section = JSON.parse(element.dataset.section);\n"
        . "                        var category = JSON.parse(element.dataset.category);\n"
        . "                        \n"
        . "                        var sectionFilter = [];\n"
        . "                        var sectionCategory = [];\n"
        . "        \n"
        . "                        var checkboxes = document.querySelectorAll('.section:checked');\n"
        . "        \n"
        . "                        for (var i = 0; i < checkboxes.length; i++) {\n"
        . "                            sectionFilter.push(checkboxes[i].value)\n"
        . "                        }\n"
        . "        \n"
        . "                        if (section.some(r=> sectionFilter.includes(r))) {\n"
        . "                            var showSection = true;\n"
        . "                        }else{\n"
        . "                            var showSection = false;\n"
        . "                        }\n"
        . "        \n"
        . "                        var checkboxes = document.querySelectorAll('.category:checked');\n"
        . "        \n"
        . "                        for (var i = 0; i < checkboxes.length; i++) {\n"
        . "                            sectionCategory.push(checkboxes[i].value)\n"
        . "                        }\n"
        . "        \n"
        . "                        if (category.some(r=> sectionCategory.includes(r))) {\n"
        . "                            var showCategory = true;\n"
        . "                        }else{\n"
        . "                            var showCategory = false;\n"
        . "                        }\n"
        . "        \n"
        . "                        if (showSection && showCategory) {\n"
        . "                            if (element.checked) {\n"
        . "                                element.classList.remove('bg-danger');\n"
        . "                                element.classList.remove('border-danger');\n"
        . "                                element.setAttribute('onclick', '');\n"
        . "                            }\n"
        . "                            element.parentElement.style.display = 'block';\n"
        . "                            element.disabled = false;\n"
        . "                        }else{\n"
        . "                            if (element.checked) {\n"
        . "                                element.disabled = false;\n"
        . "                                element.classList.add('bg-danger');\n"
        . "                                element.classList.add('border-danger');\n"
        . "                                element.setAttribute('onclick', \"disabledCheckbox(this)\");\n"
        . "                                element.parentElement.style.display = 'block';\n"
        . "                            } else {\n"
        . "                                element.disabled = true;\n"
        . "                                element.parentElement.style.display = 'none';\n"
        . "                            }\n"
        . "                        }\n"
        . "        \n"
        . "                    });\n"
        . "                }\n"
        . "\n"
        . "                filterSubcategory();\n"
        . "                \$('.category').click(function(){\n"
        . "                    filterCategory();\n"
        . "                });\n"
        . "\n"
        . "                </script>";
});

check('ogni funzione che lo script usa e\' definita una volta sola, in qualsiasi ordine dei filtri', function () {
    $ordini = [
        ['section', 'category'], ['category', 'section'],
        ['section', 'subcategory'], ['subcategory', 'section'],
        ['section', 'category', 'subcategory'], ['section', 'subcategory', 'category'],
        ['category', 'section', 'subcategory'], ['category', 'subcategory', 'section'],
        ['subcategory', 'section', 'category'], ['subcategory', 'category', 'section'],
    ];

    foreach ($ordini as $ordine) {
        $script = scriptFiltri(formSezioni($ordine)->html);

        foreach (['disabledCheckbox', 'filterCategory', 'filterSubcategory'] as $funzione) {
            $definizioni = substr_count($script, "function {$funzione}(");

            if (substr_count($script, "{$funzione}(") > $definizioni && $definizioni !== 1) {
                throw new RuntimeException(implode(', ', $ordine).": {$funzione}() usata ma definita {$definizioni} volte");
            }
        }
    }

    return true;
});

check('sezione e sottocategoria senza categoria: nessuna chiamata a filterCategory()', function () {
    $script = scriptFiltri(formSezioni(['section', 'subcategory'])->html);

    return $script !== '' && !str_contains($script, 'filterCategory');
});

check('sezione e sottocategoria senza categoria: le sottocategorie seguono solo le sezioni, come le categorie', function () {
    $categorie = scriptFiltri(formSezioni(['section', 'category'])->html);

    return $categorie !== ''
        && scriptFiltri(formSezioni(['section', 'subcategory'])->html)
            === str_replace(['filterCategory', "'.category'"], ['filterSubcategory', "'.subcategory'"], $categorie);
});

check('sezione e sottocategoria senza categoria: un clic su una sezione ricalcola le sottocategorie', function () {
    return str_contains(
        scriptFiltri(formSezioni(['section', 'subcategory'])->html),
        "\$('.section').click(function(){\n                    filterSubcategory();\n                });"
    );
});

echo "\nClasse dei campi CheckGroup\n";

check('senza classe: input di bootstrap come prima, figli compresi, con data-wi-check una volta', function () {
    return inputOpzioni(gruppoCategorie()->value(['6'])->render('bootstrap')) === [
        '<input class="form-check-input" type="checkbox" name="category[]" value="5" id="checkbox-category[]-5" data-wi-check="true" data-section="[&quot;1&quot;,&quot;2&quot;]">',
        '<input class="form-check-input" type="checkbox" name="category[]" value="7" id="checkbox-category[]-7" data-wi-check="true">',
        '<input class="form-check-input" type="checkbox" name="category[]" value="6" id="checkbox-category[]-6" data-wi-check="true" checked>',
    ];
});

check('senza classe: pillole di bootstrap come prima, con data-wi-check una volta', function () {
    return inputOpzioni(gruppoCategorie()->value(['6'])->pills()->render('bootstrap')) === [
        '<input class="btn-check" type="checkbox" name="category[]" value="5" id="checkbox-category[]-5" autocomplete="off" data-wi-check="true">',
        '<input class="btn-check" type="checkbox" name="category[]" value="6" id="checkbox-category[]-6" autocomplete="off" data-wi-check="true" checked>',
    ];
});

check('senza classe: input del tema wonder come prima, con data-wi-check una volta', function () {
    return senzaIdCasuali(inputOpzioni(gruppoCategorie()->render('wonder'))) === [
        '<input type="checkbox" id="checkbox_ID" class="wi-checkbox" name="category[]" value="5" data-wi-check="true" data-section="[&quot;1&quot;,&quot;2&quot;]">',
        '<input type="checkbox" id="checkbox_ID" class="wi-checkbox" name="category[]" value="6" data-wi-check="true">',
    ];
});

check('con la classe (bootstrap): un solo attributo class, dopo la classe del tema, anche sui figli', function () {
    return inputOpzioni(gruppoCategorie()->value(['6'])->attributes(AttributeString::parse('class="category"'))->render('bootstrap')) === [
        '<input class="form-check-input category" type="checkbox" name="category[]" value="5" id="checkbox-category[]-5" data-wi-check="true" data-section="[&quot;1&quot;,&quot;2&quot;]">',
        '<input class="form-check-input category" type="checkbox" name="category[]" value="7" id="checkbox-category[]-7" data-wi-check="true">',
        '<input class="form-check-input category" type="checkbox" name="category[]" value="6" id="checkbox-category[]-6" data-wi-check="true" checked>',
    ];
});

check('con la classe (bootstrap, radio): stessi input di prima, con la classe in coda a quella del tema', function () {
    $prima = inputOpzioni(gruppoCategorie('radio')->value('6')->render('bootstrap'));
    $con = inputOpzioni(gruppoCategorie('radio')->value('6')->attributes(AttributeString::parse('class="category"'))->render('bootstrap'));

    return count($prima) === 3
        && $con === str_replace('class="form-check-input"', 'class="form-check-input category"', $prima);
});

check('con la classe (pillole di bootstrap): un solo attributo class, dopo btn-check', function () {
    $prima = inputOpzioni(gruppoCategorie()->value(['6'])->pills()->render('bootstrap'));
    $con = inputOpzioni(gruppoCategorie()->value(['6'])->pills()->attributes(AttributeString::parse('class="category"'))->render('bootstrap'));

    return count($prima) === 2
        && $con === str_replace('class="btn-check"', 'class="btn-check category"', $prima);
});

check('con la classe (wonder): un solo attributo class, dopo wi-checkbox', function () {
    $prima = senzaIdCasuali(inputOpzioni(gruppoCategorie()->render('wonder')));
    $con = senzaIdCasuali(inputOpzioni(gruppoCategorie()->attributes(AttributeString::parse('class="category"'))->render('wonder')));

    return count($prima) === 2
        && $con === str_replace('class="wi-checkbox"', 'class="wi-checkbox category"', $prima);
});

check('class() e addClass(): classi divise sugli spazi, senza doppioni, quella del tema per prima', function () {
    $input = inputOpzioni(gruppoCategorie()->class('category  extra')->addClass('category')->addClass('form-check-input')->render('bootstrap'));

    return count($input) === 3
        && array_map('classeInput', $input) === array_fill(0, 3, 'form-check-input category extra');
});

check('classe con le virgolette: resta escapata dentro l\'unico attributo class', function () {
    $input = inputOpzioni(gruppoCategorie()->attributes(['class' => 'category" onclick="alert(1)'])->render('bootstrap'));

    foreach ($input as $campo) {
        if (classeInput($campo) !== 'form-check-input category&quot; onclick=&quot;alert(1)' || str_contains($campo, 'onclick="')) {
            return false;
        }
    }

    return count($input) === 3;
});

echo "\nClassi dei filtri a cascata\n";

check('filterCustomRendersCheck(): checkbox e radio escono con check(), i radio corti senza ricerca con select()', function () {
    $quattro = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];
    $cinque = $quattro + [5 => 'e'];

    return filterCustomRendersCheck('checkbox', '', []) === true
        && filterCustomRendersCheck('checkbox', '', $quattro) === true
        && filterCustomRendersCheck('radio', '', $quattro) === false
        && filterCustomRendersCheck('radio', '', $cinque) === true
        && filterCustomRendersCheck('radio', true, $quattro) === true
        && filterCustomRendersCheck('select', '', $cinque) === false
        && filterCustomRendersCheck('tree', '', $cinque) === false
        && filterCustomRendersCheck('', '', $cinque) === false;
});

check('filterCascadeClasses(): sezione, categoria e sottocategoria checkbox hanno la classe, in qualsiasi ordine', function () {
    return classiCascata(['section' => 'checkbox', 'category' => 'checkbox', 'subcategory' => 'checkbox']) === ['section', 'category', 'subcategory']
        && classiCascata(['subcategory' => 'checkbox', 'category' => 'checkbox', 'section' => 'checkbox']) === ['section', 'category', 'subcategory'];
});

check('filterCascadeClasses(): senza una sezione con check() nessuna classe', function () {
    $tutti = ['category' => 'checkbox', 'subcategory' => 'checkbox'];

    return classiCascata($tutti) === []
        && classiCascata(['section' => 'select'] + $tutti) === []
        && classiCascata(['section' => 'tree'] + $tutti) === []
        && classiCascata(['section' => 'radio'] + $tutti) === []
        && classiCascata(['section' => ['name' => 'Sezione']] + $tutti) === [];
});

check('filterCascadeClasses(): sezione radio con 5 opzioni o con la ricerca, cioe\' con check(): classi', function () {
    $cinque = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd', 5 => 'e'];

    return classiCascata(['section' => 'radio', 'category' => 'checkbox'], ['section' => $cinque]) === ['section', 'category']
        && classiCascata(['section' => ['type' => 'radio', 'search' => true], 'category' => 'checkbox']) === ['section', 'category'];
});

check('filterCascadeClasses(): categoria senza check(), le sottocategorie non hanno la classe', function () {
    return classiCascata(['section' => 'checkbox', 'category' => 'select', 'subcategory' => 'checkbox']) === []
        && classiCascata(['section' => 'checkbox', 'category' => 'tree', 'subcategory' => 'checkbox']) === []
        && classiCascata(['section' => 'checkbox', 'category' => 'radio', 'subcategory' => 'checkbox']) === [];
});

check('filterCascadeClasses(): sottocategoria senza check(), la categoria tiene la classe', function () {
    return classiCascata(['section' => 'checkbox', 'category' => 'checkbox', 'subcategory' => 'select']) === ['section', 'category'];
});

check('filterCascadeClasses(): senza categoria, le sottocategorie seguono le sezioni', function () {
    return classiCascata(['section' => 'checkbox', 'subcategory' => 'checkbox']) === ['section', 'subcategory']
        && classiCascata(['section' => 'checkbox', 'subcategory' => 'select']) === [];
});

check('filterCascadeClasses(): la sola sezione, o con altri filtri, non ha la classe', function () {
    return classiCascata(['section' => 'checkbox']) === []
        && classiCascata(['section' => 'checkbox', 'stato' => 'checkbox', 'visible' => 'radio']) === [];
});

echo "\nFiltri a cascata con i campi veri (processo a parte)\n";

check('sezione, categoria e sottocategoria checkbox: ogni input ha la classe del suo filtro', function () {
    return classiOpzioni(formVero([
        'section' => ['name' => 'Sezione', 'type' => 'checkbox', 'database' => true],
        'category' => ['name' => 'Categoria', 'type' => 'checkbox'],
        'subcategory' => ['name' => 'Sottocategoria', 'type' => 'checkbox'],
    ])) === [
        'section[]=1' => 'form-check-input section',
        'section[]=2' => 'form-check-input section',
        'category[]=5' => 'form-check-input category',
        'subcategory[]=8' => 'form-check-input subcategory',
    ];
});

check('sezione e sottocategoria checkbox, senza categoria: la classe su sezioni e sottocategorie', function () {
    return classiOpzioni(formVero([
        'section' => ['name' => 'Sezione', 'type' => 'checkbox', 'database' => true],
        'subcategory' => ['name' => 'Sottocategoria', 'type' => 'checkbox'],
    ])) === [
        'section[]=1' => 'form-check-input section',
        'section[]=2' => 'form-check-input section',
        'subcategory[]=8' => 'form-check-input subcategory',
    ];
});

check('sezione radio con 3 opzioni (una select): categorie e sottocategorie senza classe', function () {
    return classiOpzioni(formVero([
        'section' => ['name' => 'Sezione', 'type' => 'radio', 'database' => true],
        'category' => ['name' => 'Categoria', 'type' => 'checkbox'],
        'subcategory' => ['name' => 'Sottocategoria', 'type' => 'checkbox'],
    ])) === [
        'category[]=5' => 'form-check-input',
        'subcategory[]=8' => 'form-check-input',
    ];
});

check('con i campi veri lo script resta quello fissato sopra', function () {
    return scriptFiltri(formVero([
        'section' => ['name' => 'Sezione', 'type' => 'checkbox', 'database' => true],
        'category' => ['name' => 'Categoria', 'type' => 'checkbox'],
        'subcategory' => ['name' => 'Sottocategoria', 'type' => 'checkbox'],
    ])) === scriptFiltri(formSezioni(['section', 'category', 'subcategory'])->html);
});

summary();
