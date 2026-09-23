<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\QuickCreateAuthorizer;
use Wonder\Backend\Support\QuickCreateController;
use Wonder\Backend\Support\QuickCreatePanel;

$checks = 0;
$check = static function (bool $c, string $m) use (&$checks): void {
    if (!$c) { throw new RuntimeException($m); }
    $checks++;
};

// --- Task 1: HasQuickCreate concern (firma con default + layout) ------------

$fakeResource = new class {
    public static function slug(): string { return 'category'; }
};
$fakeClass = get_class($fakeResource);

$input = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($fakeClass, ['name'], label: 'name');
$config = ($input->get()['context']['quick_create'] ?? null);

$check(is_array($config), 'quick_create config stored');
$check($config['slug'] === 'category', 'slug resolved from target');
$check($config['fields'] === ['name'], 'subset fields stored');
$check($config['label'] === 'name', 'label field stored');
$check($config['resource'] === $fakeClass, 'target class stored');
$check(array_key_exists('layout', $config) && $config['layout'] === null, 'layout null by default');

$defConfig = FormField::key('c')->select([])->quickCreate($fakeClass)->get()['context']['quick_create'];
$check(array_key_exists('fields', $defConfig) && $defConfig['fields'] === null, 'fields null when omitted (required resolved later)');

// --- Task 2: QuickCreateAuthorizer ------------------------------------------

$permResource = new class {
    public static function slug(): string { return 'category'; }
    public static function permissionSchema(): object {
        return new class {
            public function get(string $k): array {
                return $k === 'backend' ? ['create' => ['admin', 'editor']] : [];
            }
        };
    }
};
$permClass = get_class($permResource);

$check(QuickCreateAuthorizer::createAuthority($permClass) === ['admin', 'editor'], 'reads backend.create authority');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['editor']) === true, 'intersecting authority allowed');
$check(QuickCreateAuthorizer::userCanCreate($permClass, ['viewer']) === false, 'non-intersecting denied');

$readonlyResource = new class {
    public static function slug(): string { return 'tax-category'; }
    public static function isReadonly(): bool { return true; }
    public static function permissionSchema(): object {
        return new class {
            public function get(string $k): array { return []; }
        };
    }
};
$check(QuickCreateAuthorizer::userCanCreate(get_class($readonlyResource), ['admin']) === false, 'readonly resource denied');

// --- Task 3: QuickCreateController::payload (strip control keys) -------------

$vals = QuickCreateController::payload([
    'resource' => 'category', 'quick_label' => 'name', 'quick_fields' => ['name'],
    'name' => 'Scarpe', 'slug' => 'scarpe',
]);
$check($vals === ['name' => 'Scarpe', 'slug' => 'scarpe'], 'payload strips control keys, keeps data');

// --- QuickCreatePanel: required fields, fields(), label() --------------------

$target = new class {
    public static function slug(): string { return 'category'; }
    public static function permissionSchema(): object {
        return new class { public function get($k): array { return []; } };
    }
    public static function formSchema(): array {
        return [
            FormField::key('name')->text()->required(),
            FormField::key('note')->textarea(),
            FormField::key('slug')->text()->required(),
        ];
    }
    public static function getInput(string $key): object { return FormField::key($key)->text(); }
};
$targetClass = get_class($target);

$check(QuickCreatePanel::requiredFields($targetClass) === ['name', 'slug'], 'required fields detected');
$check(QuickCreatePanel::fields(['resource' => $targetClass, 'fields' => null]) === ['name', 'slug'], 'fields() default = required');
$check(QuickCreatePanel::fields(['resource' => $targetClass, 'fields' => ['note']]) === ['note'], 'fields() honors subset');
$check(QuickCreatePanel::label(['label' => null], ['note', 'name']) === 'name', 'label() prefers name');
$check(QuickCreatePanel::label(['label' => 'note'], ['note']) === 'note', 'label() honors declared');

// buttonLabel(): nome leggibile della risorsa (label()), ripiego sullo slug.
$named = new class {
    public static function slug(): string { return 'tag'; }
    public static function label(): string { return 'Tag'; }
};
$check(QuickCreatePanel::buttonLabel(['resource' => get_class($named), 'slug' => 'tag']) === 'Aggiungi Tag', 'buttonLabel() uses resource label()');
$check(QuickCreatePanel::buttonLabel(['resource' => $targetClass, 'slug' => 'category']) === 'Aggiungi category', 'buttonLabel() falls back to slug');

// --- missingRequired: impedisce la riga vuota (campi obbligatori vuoti) ------

$check(QuickCreateController::missingRequired($targetClass, ['name' => 'Scarpe', 'slug' => 'scarpe']) === [], 'missingRequired: tutti pieni => nessuno');
$check(QuickCreateController::missingRequired($targetClass, ['name' => '', 'slug' => 'scarpe']) === ['name'], 'missingRequired: obbligatorio vuoto => flag');
$check(QuickCreateController::missingRequired($targetClass, ['name' => '  ', 'slug' => '']) === ['name', 'slug'], 'missingRequired: whitespace/empty => entrambi');
$check(QuickCreateController::missingRequired($targetClass, ['slug' => 'x']) === [], 'missingRequired: campo assente (non mostrato) => saltato');
$check(QuickCreateController::missingRequired($targetClass, ['name' => ['', ''], 'slug' => 'x']) === ['name'], 'missingRequired: array tutto vuoto => flag');
$check(QuickCreateController::missingRequired($targetClass, ['name' => ['', 'a'], 'slug' => 'x']) === [], 'missingRequired: array con un valore => ok');

// --- Task 4: Bootstrap renderer emits "+" + modal ---------------------------

$html = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($targetClass, ['name'], label: 'name')->render('bootstrap');

$check(str_contains($html, 'data-wi-quick-create'), 'renders the quick-create trigger');
$check(str_contains($html, 'name="resource"'), 'emits the target slug hidden field');
$check(str_contains($html, 'name="quick_label"'), 'emits the label hidden field');
$check(str_contains($html, 'data-wi-qc-family="select"'), 'tags the input family');

// Placement select = "+" attaccato nell'input-group, versione floating.
$check(str_contains($html, 'class="btn btn-outline-secondary" data-wi-quick-create'), 'select attaches the "+" in the input-group');
$check(str_contains($html, 'input-group') && str_contains($html, 'form-floating'), 'select keeps the floating input inside the input-group');

// Placement checkbox = testata "Aggiungi <Nome>" in alto a destra (position-absolute),
// non più un bottone sotto.
$checkHtml = FormField::key('tags')->checkbox()->quickCreate($targetClass, ['name'], label: 'name')->render('bootstrap');
$check(str_contains($checkHtml, 'data-wi-qc-family="checkbox"'), 'checkbox family tagged');
$check(str_contains($checkHtml, 'wi-qc-header" data-wi-quick-create'), 'checkbox renders the "+" as a top-right header');
$check(str_contains($checkHtml, 'position-absolute top-0 end-0'), 'checkbox header is pinned top-right');
$check(str_contains($checkHtml, '> Aggiungi category</button>'), 'checkbox header names the resource');
$check(!str_contains($checkHtml, 'btn-outline-secondary btn-sm'), 'checkbox no longer uses the button-below markup');

$plain = FormField::key('category_id')->select(['1' => 'A'])->render('bootstrap');
$check(!str_contains($plain, 'data-wi-quick-create'), 'no trigger when not declared');

// --- Il modal vive dentro il form della Resource ----------------------------
// Un <form> annidato il browser lo scarta in fase di parsing: restava un
// bottone submit che salvava il record, e i campi del modal venivano postati
// insieme a quelli della scheda (un `name` nel modal vinceva su quello del
// record). Niente form annidato, bottone non-submit, e il JS sposta i modal
// in fondo al body.

$check(!str_contains($html, '<form class="wi-qc-form"'), 'no nested form inside the resource form');
$check(str_contains($html, '<div class="wi-qc-form">'), 'the modal body is a plain container');
$check(!str_contains($html, 'type="submit"'), 'no submit button that would save the record');
$check(str_contains($html, 'wi-qc-submit'), 'the save button is bound by class');
$check(str_contains($html, 'data-wi-qc-endpoint'), 'the modal carries its endpoint');
$check(str_contains($html, 'function detachModals'), 'the client moves the modals out of the form');
$check(str_contains($html, 'function valuesOf'), 'the client collects the fields by itself');
$check(str_contains($html, 'function invalidFields'), 'the client checks validity before posting');
$check(str_contains($html, 'Compila i campi obbligatori'), 'the client surfaces the required-fields alert');

// --- Sesto giro: bottone con testo proprio, pillole in linea -----------------
// Il testo del "+" si può dire con `button:`: «Aggiungi opzione» invece del
// nome della risorsa. Lo stesso testo fa da titolo al modal.

$check(QuickCreatePanel::buttonLabel(['resource' => $targetClass, 'slug' => 'category', 'button' => 'Aggiungi opzione']) === 'Aggiungi opzione', 'buttonLabel() honors the declared text');
$declared = FormField::key('c')->select([])->quickCreate($targetClass, button: 'Aggiungi opzione')->get()['context']['quick_create'];
$check(($declared['button'] ?? null) === 'Aggiungi opzione', 'button text stored in the config');

// Pillole: il "+" è l'ultima pillola della riga, non una testata in alto.
$pillsHtml = FormField::key('sizes')->checkbox()->options(['1' => 'S', '2' => 'M'])->pills()
    ->quickCreate($targetClass, ['name'], label: 'name', button: 'Aggiungi opzione')->render('bootstrap');
$check(str_contains($pillsHtml, 'wi-qc-inline'), 'pills render the "+" inline');
$check(!str_contains($pillsHtml, 'wi-qc-header'), 'pills drop the top-right header');
$check(str_contains($pillsHtml, '> Aggiungi opzione</button>'), 'the inline "+" uses the declared text');
$check(strpos($pillsHtml, 'wi-qc-inline') > strpos($pillsHtml, 'for="checkbox-sizes[]-2"'), 'the inline "+" comes after the pills');
$check(substr_count($pillsHtml, 'class="modal fade"') === 1, 'the modal is printed once');
$check(str_contains($pillsHtml, '<h5 class="modal-title">Aggiungi opzione</h5>'), 'the modal title repeats the button text');

// Script: la voce nuova nasce pillola, spuntata, e lo dice con `change`.
$script = $html.$checkHtml.$pillsHtml;
$check(str_contains($script, 'function appendPill'), 'the client knows how to add a pill');
$check(str_contains($script, "new Event('change', { bubbles: true })"), 'the new check fires change');
$check(str_contains($script, 'defaultValue'), 'resetFields restores the defaults');

// La risposta porta la riga: chi la riceve sa, per esempio, sotto quale
// genitore metterla.
$check(QuickCreateController::responseItem(['id' => 9, 'name' => 'Polo'], ['name' => 'Polo', 'parent_id' => '4']) === ['name' => 'Polo', 'parent_id' => '4', 'id' => 9], 'responseItem merges posted values under the stored row');
$check(QuickCreateController::responseItem(['id' => 9], ['tags' => ['a', 'b']]) === ['id' => 9], 'responseItem keeps only scalar posted values');

echo "OK: {$checks} checks passed\n";
