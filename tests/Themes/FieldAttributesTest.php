<?php
/** php tests/Themes/FieldAttributesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Modal;
use Wonder\Elements\Form\Components\CheckGroup;
use Wonder\Elements\Form\Components\DynamicCheck;
use Wonder\Elements\Form\Components\SelectDate;

/**
 * Un attributo per tag, e la classe del campo accanto a quella del tema.
 *
 * Il renderer scrive a mano gli attributi del tema (`class="form-control"`,
 * `data-wi-check`, `placeholder`, `readonly`, …) e poi quelli del campo: se il
 * campo ha la stessa chiave, il tag la ripete e il browser tiene la prima. Così
 * la classe data al campo spariva dietro quella del tema. L'HTML qui si legge
 * come lo scrive il renderer, doppioni compresi: DOMDocument, come il browser,
 * terrebbe solo il primo.
 */

defined('APP_URL') || define('APP_URL', 'https://example.test');
defined('APP_VERSION') || define('APP_VERSION', 'dev');

/**
 * I tag del frammento, con gli attributi nell'ordine in cui sono scritti.
 *
 * @return list<array{tag: string, attributi: list<array{0: string, 1: ?string}>}>
 */
function tagDi(string $html): array
{
    $html = (string) preg_replace('#<script\b[^>]*>.*?</script>#si', '<script>', $html);
    preg_match_all('#<([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^\s"\'=<>/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*)\s*/?>#s', $html, $trovati, PREG_SET_ORDER);

    // Virgolette non escapate dentro un valore spezzano il tag: la regola non
    // lo riconosce più e il conto non torna.
    if (preg_match_all('#<[a-zA-Z]#', $html) !== count($trovati)) {
        throw new RuntimeException('markup non valido');
    }

    $tag = [];

    foreach ($trovati as $trovato) {
        preg_match_all('#([^\s"\'=<>/]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?#', $trovato[2], $coppie, PREG_SET_ORDER);
        $attributi = [];

        foreach ($coppie as $coppia) {
            $valore = $coppia[2] ?? null;

            if ($valore !== null && in_array($valore[0], ['"', "'"], true)) {
                $valore = substr($valore, 1, -1);
            }

            $attributi[] = [strtolower($coppia[1]), $valore];
        }

        $tag[] = ['tag' => strtolower($trovato[1]), 'attributi' => $attributi];
    }

    return $tag;
}

/** @return string[] le chiavi scritte più di una volta sullo stesso tag */
function doppioni(array $tag): array
{
    $conti = array_count_values(array_column($tag['attributi'], 0));

    return array_keys(array_filter($conti, static fn (int $volte): bool => $volte > 1));
}

/** @return string[] le classi che il browser applica: quelle del primo `class` */
function classiDel(array $tag): array
{
    foreach ($tag['attributi'] as [$nome, $valore]) {
        if ($nome === 'class') {
            return array_values(array_filter(preg_split('/\s+/', trim((string) $valore)) ?: [], static fn (string $classe): bool => $classe !== ''));
        }
    }

    return [];
}

/** @return list<?string> i valori di ogni occorrenza della chiave sul tag */
function valoriDi(array $tag, string $chiave): array
{
    $valori = [];

    foreach ($tag['attributi'] as [$nome, $valore]) {
        if ($nome === $chiave) {
            $valori[] = $valore;
        }
    }

    return $valori;
}

/**
 * Ogni componente form con le forme che il renderer distingue.
 *
 * Restano fuori `Repeater`, che compone i campi delle sue colonne e ha i suoi
 * test, e `SortableInput`, deprecato.
 *
 * @return array<string, array<string, callable(object): object>>
 */
function componenti(): array
{
    $componenti = [];

    foreach (glob(dirname(__DIR__, 2).'/class/Elements/Form/Components/*.php') ?: [] as $file) {
        $nome = basename($file, '.php');

        if (in_array($nome, ['Repeater', 'SortableInput'], true)) {
            continue;
        }

        $componenti[$nome] = match ($nome) {
            'File' => [
                'trascina' => static fn (object $campo): object => $campo,
                'classico' => static fn (object $campo): object => $campo->mode('classic'),
            ],
            'Select' => [
                'base' => static fn (object $campo): object => $campo,
                'legacy' => static fn (object $campo): object => $campo->schema('legacy_container', true),
            ],
            'CheckGroup' => [
                'base' => static fn (object $campo): object => $campo,
                'pillole' => static fn (object $campo): object => $campo->pills(),
                'radio' => static fn (object $campo): object => $campo->inputType('radio'),
            ],
            default => ['base' => static fn (object $campo): object => $campo],
        };
    }

    ksort($componenti);

    return $componenti;
}

/**
 * Il campo `campo` reso nel tema, o null se il tema non ha quel renderer:
 * fra i temi non c'è ripiego, ed è voluto.
 */
function campo(string $nome, string $tema, callable $forma, ?callable $ritocco = null): ?string
{
    $classe = 'Wonder\\Elements\\Form\\Components\\'.$nome;
    $campo = new $classe('campo');

    if (method_exists($campo, 'label')) {
        $campo->label('Etichetta');
    }

    if (method_exists($campo, 'options')) {
        $campo->options(['1' => 'Uno', '2' => 'Due']);
    }

    $forma($campo);

    if ($ritocco !== null) {
        $ritocco($campo);
    }

    try {
        return $campo->render($tema);
    } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'Nessun renderer trovato')) {
            return null;
        }

        throw $e;
    }
}

/** @param string[] $errori */
function nessunErrore(array $errori): bool
{
    if ($errori === []) {
        return true;
    }

    $errori = array_values(array_unique($errori));

    throw new RuntimeException(count($errori)." casi, fra cui:\n      ".implode("\n      ", array_slice($errori, 0, 12)));
}

$ritocchi = [
    'nessuno' => static fn (object $campo): object => $campo,
    'required, disabled, readonly, autocomplete' => static fn (object $campo): object => method_exists($campo, 'required')
        ? $campo->required()->disabled()->readonly()->autocomplete('off')
        : $campo,
    'placeholder, style, checked, maxlength' => static fn (object $campo): object => $campo
        ->attr('placeholder', 'p')->attr('style', 'color:red')->attr('checked', true)->attr('maxlength', '5'),
    'class()' => static fn (object $campo): object => $campo->class('evidenza'),
    "attr('class')" => static fn (object $campo): object => $campo->attr('class', 'evidenza'),
];

echo "\nUn attributo per tag\n";

check('nessun tag ripete un attributo, in nessun campo dei due temi', function () use ($ritocchi) {
    $errori = [];

    foreach (componenti() as $nome => $forme) {
        foreach (['bootstrap', 'wonder'] as $tema) {
            foreach ($forme as $nomeForma => $forma) {
                foreach ($ritocchi as $nomeRitocco => $ritocco) {
                    $caso = "{$nome} ({$tema}, {$nomeForma}, {$nomeRitocco})";

                    try {
                        $html = campo($nome, $tema, $forma, $ritocco);

                        foreach ($html === null ? [] : tagDi($html) as $tag) {
                            if (($doppi = doppioni($tag)) !== []) {
                                $errori[] = "{$caso} <{$tag['tag']}> ripete ".implode(', ', $doppi);
                            }
                        }
                    } catch (Throwable $e) {
                        $errori[] = "{$caso}: ".get_class($e).' '.strtok($e->getMessage(), "\n");
                    }
                }
            }
        }
    }

    return nessunErrore($errori);
});

check('i valori che il browser vede restano quelli del tema, scritti una volta', function () {
    $casi = [
        ['InputColor', 'bootstrap', 'input', 'placeholder', 'Etichetta', static fn ($campo) => $campo->attr('placeholder', 'p')],
        ['DatePicker', 'wonder', 'input', 'placeholder', 'gg/mm/aaaa', static fn ($campo) => $campo->attr('placeholder', 'p')],
        ['Textarea', 'bootstrap', 'textarea', 'style', 'height: 100px', static fn ($campo) => $campo->attr('style', 'color:red')],
        ['CheckGroup', 'bootstrap', 'input', 'autocomplete', 'off', static fn ($campo) => $campo->pills()->autocomplete('on')],
        ['Checkbox', 'bootstrap', 'input', 'checked', null, static fn ($campo) => $campo->attr('checked', true)],
        ['DateRange', 'bootstrap', 'input', 'readonly', null, static fn ($campo) => $campo->readonly()],
        ['GoogleAddress', 'wonder', 'input', 'disabled', null, static fn ($campo) => $campo->disabled()],
        ['InputPassword', 'wonder', 'input', 'data-wi-check', 'true', static fn ($campo) => $campo],
        ['TextareaEditor', 'bootstrap', 'textarea', 'data-wi-check', 'true', static fn ($campo) => $campo],
    ];
    $errori = [];

    foreach ($casi as [$nome, $tema, $elemento, $chiave, $atteso, $ritocco]) {
        $trovati = 0;

        foreach (tagDi((string) campo($nome, $tema, static fn ($campo) => $campo, $ritocco)) as $tag) {
            $valori = valoriDi($tag, $chiave);

            if ($tag['tag'] !== $elemento || $valori === []) {
                continue;
            }

            $trovati++;

            if ($valori !== [$atteso]) {
                $errori[] = "{$nome} ({$tema}) {$chiave}: ".json_encode($valori).' invece di '.json_encode([$atteso]);
            }
        }

        if ($trovati === 0) {
            $errori[] = "{$nome} ({$tema}): nessun <{$elemento}> con {$chiave}";
        }
    }

    return nessunErrore($errori);
});

echo "\nClasse del campo\n";

check('class() e attr(\'class\'): la classe del campo segue quelle del tema nello stesso attributo', function () {
    $vie = [
        'class()' => static fn (object $campo): object => $campo->class('evidenza'),
        "attr('class')" => static fn (object $campo): object => $campo->attr('class', 'evidenza'),
    ];
    // Submit ha una sua regola: class() sceglie le classi del bottone al posto
    // di quelle di default e attr('class') non conta. reCAPTCHA di wonder non
    // scrive attributi del campo; DynamicCheck di bootstrap li passa alla lib,
    // che crea le caselle, dentro data-wi-attribute.
    $senzaClasse = ['Submit|bootstrap', 'Submit|wonder', 'reCAPTCHA|wonder', 'DynamicCheck|bootstrap'];
    $errori = [];

    foreach (componenti() as $nome => $forme) {
        foreach (['bootstrap', 'wonder'] as $tema) {
            if (in_array("{$nome}|{$tema}", $senzaClasse, true)) {
                continue;
            }

            foreach ($forme as $nomeForma => $forma) {
                foreach ($vie as $nomeVia => $via) {
                    $caso = "{$nome} ({$tema}, {$nomeForma}, {$nomeVia})";

                    try {
                        $senza = campo($nome, $tema, $forma);

                        if ($senza === null) {
                            continue;
                        }

                        $prima = tagDi($senza);
                        $dopo = tagDi((string) campo($nome, $tema, $forma, $via));
                    } catch (Throwable $e) {
                        $errori[] = "{$caso}: ".get_class($e).' '.strtok($e->getMessage(), "\n");
                        continue;
                    }

                    if (count($prima) !== count($dopo)) {
                        $errori[] = "{$caso}: cambia il numero di tag";
                        continue;
                    }

                    $conClasse = 0;

                    foreach ($dopo as $i => $tag) {
                        $tema_ = classiDel($prima[$i]);
                        $classi = classiDel($tag);

                        if ($classi === [...$tema_, 'evidenza']) {
                            $conClasse++;
                        } elseif ($classi !== $tema_) {
                            $errori[] = "{$caso} <{$tag['tag']}> class=\"".implode(' ', $classi).'" invece di "'.implode(' ', [...$tema_, 'evidenza']).'"';
                        }
                    }

                    if ($conClasse === 0) {
                        $errori[] = "{$caso}: la classe del campo non arriva al browser";
                    }
                }
            }
        }
    }

    return nessunErrore($errori);
});

check('la classe del campo sugli input dei due temi', function () {
    $casi = [
        ['InputText', 'bootstrap', null, ['form-control evidenza']],
        ['InputText', 'wonder', null, ['wi-input evidenza']],
        ['Select', 'bootstrap', null, ['form-select evidenza']],
        ['Checkbox', 'bootstrap', null, ['form-check-input mt-0 evidenza']],
        ['CheckBoolean', 'bootstrap', null, ['btn-check wi-true evidenza', 'btn-check wi-false evidenza']],
        ['CheckGroup', 'bootstrap', null, ['form-check-input evidenza', 'form-check-input evidenza']],
        ['CheckGroup', 'bootstrap', static fn ($campo) => $campo->pills(), ['btn-check evidenza', 'btn-check evidenza']],
        ['CheckGroup', 'wonder', null, ['wi-checkbox evidenza', 'wi-checkbox evidenza']],
        ['CheckTree', 'bootstrap', null, ['d-none evidenza', 'd-none evidenza']],
        ['File', 'bootstrap', static fn ($campo) => $campo->mode('classic'), ['form-control evidenza']],
    ];
    $errori = [];

    foreach ($casi as [$nome, $tema, $forma, $attese]) {
        $html = (string) campo($nome, $tema, $forma ?? static fn ($campo) => $campo, static fn ($campo) => $campo->class('evidenza'));
        $classi = [];

        foreach (tagDi($html) as $tag) {
            if (in_array('evidenza', classiDel($tag), true)) {
                $classi[] = implode(' ', classiDel($tag));
            }
        }

        if ($classi !== $attese) {
            $errori[] = "{$nome} ({$tema}): ".json_encode($classi).' invece di '.json_encode($attese);
        }
    }

    return nessunErrore($errori);
});

check('addClass() dopo attr(\'class\', stringa): aggiunge la classe invece di lanciare un TypeError', function () {
    $campo = (new CheckGroup('tag'))->label('Tag')->options(['1' => 'Uno'])->attr('class', 'a')->addClass('b');
    $classi = [];

    foreach (tagDi($campo->render('bootstrap')) as $tag) {
        if (valoriDi($tag, 'type') === ['checkbox']) {
            $classi[] = implode(' ', classiDel($tag));
        }
    }

    return $campo->getAttr('class') === ['a', 'b']
        && $classi === ['form-check-input a b']
        && (new CheckGroup('tag'))->attr('class', '')->addClass('b')->getAttr('class') === ['b'];
});

check('Modal: class() o attr(\'class\'), poi addClass(), escono dopo modal fade', function () {
    return str_contains(Modal::make('Costo')->class('uno')->addClass('due')->render('bootstrap'), 'class="modal fade uno due"')
        && str_contains(Modal::make('Costo')->attr('class', 'uno')->addClass('due')->render('bootstrap'), 'class="modal fade uno due"');
});

echo "\nCasi singoli\n";

check('DynamicCheck (bootstrap): gli attributi per le caselle della lib stanno escapati in data-wi-attribute, senza data-wi-check', function () {
    $html = (new DynamicCheck('campo'))->label('Etichetta')->url('/api/cerca')->class('evidenza')->render('bootstrap');
    $ricerca = null;

    foreach (tagDi($html) as $tag) {
        if (valoriDi($tag, 'data-wi-attribute') !== []) {
            $ricerca = $tag;
        }
    }

    if ($ricerca === null) {
        return false;
    }

    $perLeCaselle = html_entity_decode((string) valoriDi($ricerca, 'data-wi-attribute')[0], ENT_QUOTES | ENT_HTML5);

    return doppioni($ricerca) === []
        && classiDel($ricerca) === ['form-control', 'card-header', 'm-0', 'border-0', 'border-bottom', 'bg-body']
        && str_contains($perLeCaselle, 'class="evidenza')
        && !str_contains($perLeCaselle, 'data-wi-check');
});

check('SelectDate (wonder) si rende con il datepicker del tema', function () {
    $html = (new SelectDate('campo'))->label('Nascita')->render('wonder');

    return str_contains($html, 'placeholder="gg/mm/aaaa"')
        && str_contains($html, '.datepicker(options)');
});

check('check() legacy (bootstrap): un solo data-wi-check per casella', function () {
    $codice = <<<'PHP'
        require 'vendor/autoload.php';
        require 'app/function/string/common.php';
        require 'app/function/string/sanitize.php';
        require 'app/function/backend/input.php';
        \Wonder\App\Theme::set('bootstrap');
        echo check('Tag', 'tag', ['1' => 'Uno', '2' => 'Due'], 'required');
        PHP;
    $html = (string) shell_exec('cd '.escapeshellarg(dirname(__DIR__, 2)).' && '.escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($codice).' 2>&1');
    $caselle = array_values(array_filter(tagDi($html), static fn (array $tag): bool => valoriDi($tag, 'type') === ['checkbox']));

    if (count($caselle) !== 2) {
        throw new RuntimeException('caselle attese 2: '.substr($html, 0, 300));
    }

    foreach ($caselle as $casella) {
        if (valoriDi($casella, 'data-wi-check') !== ['true'] || valoriDi($casella, 'required') !== [null]) {
            return false;
        }
    }

    return true;
});

summary();
