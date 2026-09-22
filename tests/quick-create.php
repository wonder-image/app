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

// --- Task 4: Bootstrap renderer emits "+" + modal ---------------------------

$html = FormField::key('category_id')->select(['1' => 'A'])->quickCreate($targetClass, ['name'], label: 'name')->render('bootstrap');

$check(str_contains($html, 'data-wi-quick-create'), 'renders the quick-create trigger');
$check(str_contains($html, 'name="resource"'), 'emits the target slug hidden field');
$check(str_contains($html, 'name="quick_label"'), 'emits the label hidden field');
$check(str_contains($html, 'data-wi-qc-family="select"'), 'tags the input family');

// Placement: select = "+" attaccato nell'input-group (bottone senza btn-sm);
// checkbox = bottone sotto (btn-sm).
$check(str_contains($html, 'class="btn btn-outline-secondary" data-wi-quick-create'), 'select attaches the "+" in the input-group');

$checkHtml = FormField::key('tags')->checkbox()->quickCreate($targetClass, ['name'], label: 'name')->render('bootstrap');
$check(str_contains($checkHtml, 'data-wi-qc-family="checkbox"'), 'checkbox family tagged');
$check(str_contains($checkHtml, 'class="btn btn-outline-secondary btn-sm" data-wi-quick-create'), 'checkbox keeps the "+" as a button below');

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

echo "OK: {$checks} checks passed\n";
