<?php
/** php tests/Themes/QuickCreateButtonTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\QuickCreateModal;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\QuickCreateButton;

/**
 * La creazione rapida staccata da un campo: un bottone nella scheda che apre
 * lo stesso modal del "+" dei campi, con gli stessi permessi e lo stesso
 * evento. Chi ascolta l'evento riceve `input: null` e la riga intera.
 */

// La risorsa da creare: si crea solo con l'authority `admin`.
$feature = new class {
    public static function slug(): string { return 'feature'; }
    public static function label(): string { return 'Caratteristica'; }
    public static function permissionSchema(): object {
        return new class {
            public function get(string $key): array {
                return $key === 'backend' ? ['create' => ['admin']] : [];
            }
        };
    }
    public static function formSchema(): array {
        return [
            FormField::key('name')->text()->label('Nome')->required(),
            FormField::key('unit')->text()->label('Unità'),
        ];
    }
    public static function getInput(string $key): object {
        foreach (self::formSchema() as $field) {
            if ($field->name === $key) { return $field; }
        }

        return FormField::key($key)->text();
    }
};
$featureClass = get_class($feature);

// Una tabella che si modifica solo in locale: nessuno la crea da qui.
$readonly = new class {
    public static function slug(): string { return 'tax'; }
    public static function isReadonly(): bool { return true; }
    public static function permissionSchema(): object {
        return new class { public function get(string $key): array { return []; } };
    }
    public static function formSchema(): array { return [FormField::key('name')->text()->required()]; }
    public static function getInput(string $key): object { return FormField::key($key)->text(); }
};
$readonlyClass = get_class($readonly);

$asAdmin = static fn () => LegacyGlobals::set('USER', (object) ['authority' => ['admin']]);
$asViewer = static fn () => LegacyGlobals::set('USER', (object) ['authority' => ['viewer']]);
$asNobody = static fn () => LegacyGlobals::set('USER', null);

/** Tutto quello che esce in pagina in questo processo, per contare gli script. */
$page = '';

// --- Lo script condiviso esce una volta sola --------------------------------
// Va provato per primo: il flag è di processo, e il primo render della
// creazione rapida è quello che stampa lo script.

check('campo e bottone nella stessa pagina: lo script esce una volta sola', function () use ($featureClass, $asAdmin, &$page) {
    $asAdmin();

    $field = FormField::key('feature_id')->select(['1' => 'Colore'])
        ->quickCreate($featureClass, ['name'])
        ->render('bootstrap');
    $button = QuickCreateButton::make($featureClass)->text('Nuova caratteristica')->render('bootstrap');
    $page .= $field.$button;

    return substr_count($field, 'window.wiQuickCreateReady = true') === 1
        && !str_contains($button, '<script');
});

check('anche col bottone prima del campo lo script esce una volta sola', function () {
    // Il flag è di processo: l'ordine opposto si prova in un processo nuovo.
    $code = <<<'PHP'
require 'vendor/autoload.php';
$r = new class {
    public static function slug(): string { return 'feature'; }
    public static function permissionSchema(): object { return new class { public function get($k): array { return []; } }; }
    public static function formSchema(): array { return [\Wonder\App\ResourceSchema\FormField::key('name')->text()->required()]; }
    public static function getInput(string $k): object { return \Wonder\App\ResourceSchema\FormField::key($k)->text(); }
};
$c = get_class($r);
$b = \Wonder\Elements\Components\QuickCreateButton::make($c)->render('bootstrap');
$f = \Wonder\App\ResourceSchema\FormField::key('feature_id')->select([])->quickCreate($c)->render('bootstrap');
echo json_encode([substr_count($b, 'window.wiQuickCreateReady = true'), substr_count($f, '<script')]);
PHP;
    $cmd = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code);
    $out = shell_exec('cd '.escapeshellarg(dirname(__DIR__, 2)).' && '.$cmd.' 2>&1');

    return trim((string) $out) === '[1,0]' ?: throw new RuntimeException('uscita: '.trim((string) $out));
});

// --- Il bottone -------------------------------------------------------------

$render = static function (QuickCreateButton $button, string $theme = 'bootstrap') use (&$page): string {
    $html = $button->render($theme);
    $page .= $html;

    return $html;
};

check('il bottone ha l\'aspetto dei bottoni secondari del backend', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->text('Nuova caratteristica'));

    return (bool) preg_match('/<button[^>]*class="btn text-decoration-none btn-outline-secondary[^"]*"[^>]*>/', $html)
        && str_contains($html, '<i class="bi bi-plus-lg"></i> Nuova caratteristica</button>')
        && str_contains($html, 'type="button"');
});

check('il bottone apre il suo modal', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->id('nuova-caratteristica'));

    return str_contains($html, 'data-bs-toggle="modal"')
        && str_contains($html, 'data-bs-target="#wi-qc-nuova-caratteristica"')
        && str_contains($html, 'data-wi-quick-create="wi-qc-nuova-caratteristica"')
        && str_contains($html, '<div class="modal fade" id="wi-qc-nuova-caratteristica"')
        && str_contains($html, 'id="nuova-caratteristica"');
});

check('senza id due bottoni della stessa risorsa aprono due modal diversi', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $first = $render(QuickCreateButton::make($featureClass));
    $second = $render(QuickCreateButton::make($featureClass));
    preg_match('/data-bs-target="#([^"]+)"/', $first, $a);
    preg_match('/data-bs-target="#([^"]+)"/', $second, $b);

    return ($a[1] ?? '') !== '' && ($b[1] ?? '') !== '' && $a[1] !== $b[1]
        && str_contains($first, '<div class="modal fade" id="'.$a[1].'"')
        && str_contains($second, '<div class="modal fade" id="'.$b[1].'"');
});

check('nessun campo da riempire: data-wi-qc-input vuoto e famiglia button', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass));

    // Una volta sul bottone, una sul modal.
    return substr_count($html, 'data-wi-qc-input=""') === 2
        && substr_count($html, 'data-wi-qc-family="button"') === 2
        && substr_count($html, 'data-wi-qc-resource="feature"') === 2;
});

check('il modal è quello dei campi: titolo, campi nascosti, Annulla e Salva', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->text('Nuova caratteristica'));

    return str_contains($html, '<h5 class="modal-title">Nuova caratteristica</h5>')
        && str_contains($html, '<div class="wi-qc-form">')
        && str_contains($html, '<input type="hidden" name="resource" value="feature">')
        && str_contains($html, '<input type="hidden" name="quick_label" value="name">')
        && str_contains($html, 'data-bs-dismiss="modal">Annulla</button>')
        && str_contains($html, 'wi-qc-submit">Salva</button>');
});

check('senza testo il bottone dice "Aggiungi" e il nome della risorsa', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass));

    return str_contains($html, '<i class="bi bi-plus-lg"></i> Aggiungi Caratteristica</button>')
        && str_contains($html, '<h5 class="modal-title">Aggiungi Caratteristica</h5>');
});

check('senza campi il modal chiede gli obbligatori della risorsa', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass));

    return str_contains($html, 'name="name"') && !str_contains($html, 'name="unit"');
});

check('fields() sceglie i campi del modal', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->fields(['name', 'unit']));

    return str_contains($html, 'name="name"') && str_contains($html, 'name="unit"');
});

check('label() sceglie il campo che fa da etichetta', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->fields(['unit'])->label('unit'));

    return str_contains($html, '<input type="hidden" name="quick_label" value="unit">');
});

check('layout() disegna il corpo del modal', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->layout(
        static fn () => (new Container())->columns(2)->components([
            FormField::key('name')->text()->label('Nome'),
            FormField::key('unit')->text()->label('Unità'),
        ])
    ));

    // Due colonne: ogni campo prende metà riga.
    return str_contains($html, 'name="unit"') && str_contains($html, 'class="col-6"');
});

check('class() e attr() finiscono sul bottone', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();
    $html = $render(QuickCreateButton::make($featureClass)->class('ms-auto')->attr('data-wi-feature-target', 'features'));

    return (bool) preg_match('/<button[^>]*class="[^"]*btn-outline-secondary[^"]*ms-auto[^"]*"[^>]*data-wi-feature-target="features"/', $html)
        || (bool) preg_match('/<button[^>]*data-wi-feature-target="features"[^>]*class="[^"]*btn-outline-secondary[^"]*ms-auto/', $html);
});

check('size() rimpicciolisce il bottone', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();

    return str_contains($render(QuickCreateButton::make($featureClass)->size('sm')), 'btn-sm');
});

// --- Permessi ---------------------------------------------------------------

check('chi non può creare la risorsa non vede niente', function () use ($featureClass, $asViewer, $render) {
    $asViewer();

    return $render(QuickCreateButton::make($featureClass)) === '';
});

check('senza utente non si vede niente', function () use ($featureClass, $asNobody, $render) {
    $asNobody();

    return $render(QuickCreateButton::make($featureClass)) === '';
});

check('una risorsa in sola lettura non si crea da un bottone', function () use ($readonlyClass, $asAdmin, $render) {
    $asAdmin();

    return $render(QuickCreateButton::make($readonlyClass)) === '';
});

check('nella scheda il bottone prende la sua colonna', function () use ($featureClass, $asAdmin, &$page) {
    $asAdmin();
    $html = ResourceFormLayoutRenderer::renderLayout((new Card())->components([
        QuickCreateButton::make($featureClass)->text('Nuova caratteristica'),
    ]));
    $page .= $html;

    return (bool) preg_match('/<div class="col-12"><button[^>]*data-wi-quick-create=/', $html);
});

check('senza permesso nella scheda non resta una colonna vuota', function () use ($featureClass, $asViewer) {
    $asViewer();
    $html = ResourceFormLayoutRenderer::renderLayout((new Card())->components([
        QuickCreateButton::make($featureClass)->text('Nuova caratteristica'),
    ]));

    return !str_contains($html, 'data-wi-quick-create')
        && !str_contains($html, '<div class="col-12"></div>');
});

// --- Tema Wonder ------------------------------------------------------------

check('sul tema Wonder non si rompe e non stampa niente', function () use ($featureClass, $asAdmin, $render) {
    $asAdmin();

    return $render(QuickCreateButton::make($featureClass), 'wonder') === '';
});

// --- L'evento ---------------------------------------------------------------

check('in tutta la pagina lo script è uno solo', function () use (&$page) {
    return substr_count($page, 'window.wiQuickCreateReady = true') === 1;
});

check('lo script si può chiedere per intero anche dopo che è uscito', function () {
    $source = QuickCreateModal::scriptSource();

    return str_starts_with(trim($source), '<script>')
        && str_contains($source, 'window.wiQuickCreateReady = true')
        && QuickCreateModal::script() === '';
});

/** L'eseguibile di node, se c'è: lo script si prova davvero, non solo a parole. */
$node = static function (): ?string {
    $candidates = [trim((string) shell_exec('command -v node 2>/dev/null'))];
    foreach (glob((getenv('HOME') ?: '').'/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [] as $path) {
        $candidates[] = $path;
    }
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_executable($candidate)) { return $candidate; }
    }

    return null;
};

/**
 * Fa girare lo script in node con un DOM finto e preme "Salva" su un modal
 * del bottone staccato. Torna quello che l'evento ha portato.
 */
$simulate = static function (string $input) use ($node): ?array {
    $bin = $node();
    if ($bin === null) { return null; }

    $source = QuickCreateModal::scriptSource();
    $js = trim(preg_replace('#^\s*<script>|</script>\s*$#', '', $source));
    $inputJson = json_encode($input);

    $harness = <<<JS
var listeners = {}; var dispatched = []; var hidden = false;
var trigger = { id: 'trigger' };
var field = { type: 'text', name: 'name', value: 'Colore', defaultValue: '', disabled: false, tagName: 'INPUT',
  checkValidity: function () { return true; }, closest: function () { return null; } };
var box = { innerHTML: 'vecchio errore' };
var attrs = { 'data-wi-qc-endpoint': '/quick-create', 'data-wi-qc-input': {$inputJson}, 'data-wi-qc-family': 'button', 'data-wi-qc-resource': 'feature' };
var modal = { id: 'wi-qc-button-feature-1', getAttribute: function (k) { return attrs[k]; },
  querySelector: function (s) { return s === '.wi-qc-alert' ? box : null; } };
var form = { querySelectorAll: function () { return [field]; } };
var button = { closest: function (s) { return s === '.wi-qc-form' ? form : (s === '.modal' ? modal : null); } };
globalThis.window = globalThis;
globalThis.CustomEvent = function (type, init) { this.type = type; this.detail = init.detail; };
globalThis.Event = function (type) { this.type = type; };
globalThis.FormData = function () { this.entries = []; this.append = function (k, v) { this.entries.push([k, v]); }; };
globalThis.fetch = function () { return Promise.resolve({ json: function () {
  return Promise.resolve({ success: true, id: 7, label: 'Colore', item: { id: 7, name: 'Colore', unit: 'pz' } }); } }); };
window.bootstrap = { Modal: { getInstance: function () { return { hide: function () { hidden = true; } }; } } };
globalThis.document = {
  readyState: 'complete',
  addEventListener: function (t, fn) { (listeners[t] = listeners[t] || []).push(fn); },
  dispatchEvent: function (e) { dispatched.push(e); },
  getElementById: function () { return null; },
  querySelector: function (s) { return s === '[data-wi-quick-create="wi-qc-button-feature-1"]' ? trigger : null; },
  querySelectorAll: function () { return []; }
};
{$js}
listeners.click[0]({ target: { closest: function (s) { return s === '.wi-qc-submit' ? button : null; } }, preventDefault: function () {} });
setTimeout(function () {
  var e = dispatched[0] || { detail: {} };
  console.log(JSON.stringify({ type: e.type, inputIsNull: e.detail.input === null, id: e.detail.id, label: e.detail.label,
    family: e.detail.family, resource: e.detail.resource, item: e.detail.item, triggerOk: e.detail.trigger === trigger,
    reset: field.value === '', alert: box.innerHTML, hidden: hidden }));
}, 10);
JS;

    $file = tempnam(sys_get_temp_dir(), 'wiqc').'.js';
    file_put_contents($file, $harness);
    $out = shell_exec(escapeshellarg($bin).' '.escapeshellarg($file).' 2>&1');
    @unlink($file);

    $data = json_decode(trim((string) $out), true);

    if (!is_array($data)) { throw new RuntimeException('node: '.trim((string) $out)); }

    return $data;
};

check('al salvataggio l\'evento parte con input null, la riga intera e il bottone', function () use ($simulate) {
    $data = $simulate('');
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data['type'] === 'wi:quick-create:created'
        && $data['inputIsNull'] === true
        && $data['id'] === 7
        && $data['label'] === 'Colore'
        && $data['family'] === 'button'
        && $data['resource'] === 'feature'
        && $data['item'] === ['id' => 7, 'name' => 'Colore', 'unit' => 'pz']
        && $data['triggerOk'] === true;
});

check('dopo il salvataggio il modal si svuota e si chiude', function () use ($simulate) {
    $data = $simulate('');
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data['reset'] === true && $data['alert'] === '' && $data['hidden'] === true;
});

summary();
