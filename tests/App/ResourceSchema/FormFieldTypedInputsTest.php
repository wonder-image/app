<?php
/** php tests/App/ResourceSchema/FormFieldTypedInputsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Elements\Form\Components as Elements;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

# ---------------------------------------------------------------------------
# 1. ogni type-helper morfa nella classe del proprio tipo, con l'helper giusto
# ---------------------------------------------------------------------------
$types = [
    'text' => [Inputs\InputText::class, 'text'],
    'hidden' => [Inputs\InputHidden::class, 'hidden'],
    'textDate' => [Inputs\InputTextDate::class, 'textDate'],
    'textDatetime' => [Inputs\InputTextDatetime::class, 'textDatetime'],
    'dateInput' => [Inputs\InputDate::class, 'dateInput'],
    'dateRange' => [Inputs\InputDateRange::class, 'dateRange'],
    'timeInput' => [Inputs\InputTime::class, 'timeInput'],
    'color' => [Inputs\InputColor::class, 'color'],
    'email' => [Inputs\InputEmail::class, 'email'],
    'number' => [Inputs\InputNumber::class, 'number'],
    'price' => [Inputs\InputPrice::class, 'price'],
    'percentige' => [Inputs\InputPercentige::class, 'percentige'],
    'password' => [Inputs\InputPassword::class, 'password'],
    'tel' => [Inputs\InputPhone::class, 'phone'],
    'phone' => [Inputs\InputPhone::class, 'phone'],
    'url' => [Inputs\InputUrl::class, 'url'],
    'textarea' => [Inputs\InputTextarea::class, 'textarea'],
    'select' => [Inputs\InputSelect::class, 'select'],
    'bool' => [Inputs\InputSelect::class, 'select'],
    'position' => [Inputs\InputSelect::class, 'select'],
    'selectSearch' => [Inputs\InputSelectSearch::class, 'selectSearch'],
    'textList' => [Inputs\InputTextList::class, 'textList'],
    'radio' => [Inputs\InputRadio::class, 'radio'],
    'checkbox' => [Inputs\InputCheckbox::class, 'checkbox'],
    'checkTree' => [Inputs\InputCheckTree::class, 'checkTree'],
    'checkBoolean' => [Inputs\InputCheckBoolean::class, 'checkBoolean'],
    'phonePrefix' => [Inputs\InputPhonePrefix::class, 'inputPhonePrefix'],
    'repeater' => [Inputs\InputRepeater::class, 'inputRepeater'],
    'recaptcha' => [Inputs\InputReCaptcha::class, 'recaptcha'],
    'googleAddress' => [Inputs\InputGoogleAddress::class, 'googleAddress'],
    'textGenerator' => [Inputs\InputTextGenerator::class, 'textGenerator'],
];

foreach ($types as $helper => [$class, $expectedHelper]) {
    $field = FormField::key('a')->{$helper}();
    eq("{$helper}() → ".basename(str_replace('\\', '/', $class)), $field::class, $class);
    eq("{$helper}() helper", $field->get('helper'), $expectedHelper);
}

# type-helper con argomenti obbligatori
eq('file() → InputFile', FormField::key('a')->file('pdf')::class, Inputs\InputFile::class);
eq('fileDragDrop() → InputFileDragDrop', FormField::key('a')->fileDragDrop()::class, Inputs\InputFileDragDrop::class);
eq('country() → InputCountry', FormField::key('a')->country()::class, Inputs\InputCountry::class);
eq('states() → InputStates', FormField::key('a')->states()::class, Inputs\InputStates::class);
eq('searchText() → InputSearchText', FormField::key('a')->searchText('/x')::class, Inputs\InputSearchText::class);
eq('searchRadio() → InputSearchRadio', FormField::key('a')->searchRadio('/x')::class, Inputs\InputSearchRadio::class);
eq('dynamicCheck() → InputDynamicCheck', FormField::key('a')->dynamicCheck('/x')::class, Inputs\InputDynamicCheck::class);
eq('acceptDocument() → InputAcceptDocument', FormField::key('a')->acceptDocument('privacy_policy')::class, Inputs\InputAcceptDocument::class);

# ---------------------------------------------------------------------------
# 2. l'API della base resta universale: i modificatori type-specific NON ci sono
# ---------------------------------------------------------------------------
$baseMethods = array_map(
    static fn (ReflectionMethod $m): string => $m->getName(),
    (new ReflectionClass(Input::class))->getMethods(ReflectionMethod::IS_PUBLIC)
);
sort($baseMethods);
eq('API universale di Input', $baseMethods, [
    '__construct', '__toString', 'attribute', 'autocomplete', 'columnSpan', 'compile',
    'context', 'disabled', 'error', 'get', 'hasExplicitColumnSpan', 'hiddenWhen', 'inputName',
    'key', 'label', 'prepare', 'readonly', 'render', 'required', 'storeAs', 'value',
    'visibleWhen',
]);

# ...e non compaiono nemmeno sui tipi che non li supportano
foreach (['options', 'maxFile', 'requireUppercase', 'searchBar', 'uploader', 'version'] as $method) {
    eq("InputNumber non espone {$method}()", method_exists(Inputs\InputNumber::class, $method), false);
}
foreach (['decimals', 'minLength', 'maxFile'] as $method) {
    eq("InputSelect non espone {$method}()", method_exists(Inputs\InputSelect::class, $method), false);
}

# ogni tipo espone i propri
eq('InputNumber::decimals', method_exists(Inputs\InputNumber::class, 'decimals'), true);
eq('InputPrice eredita decimals', method_exists(Inputs\InputPrice::class, 'decimals'), true);
eq('InputSelect::options', method_exists(Inputs\InputSelect::class, 'options'), true);
eq('InputPassword::minLength', method_exists(Inputs\InputPassword::class, 'minLength'), true);
eq('InputFile::maxFile', method_exists(Inputs\InputFile::class, 'maxFile'), true);
eq('InputFileDragDrop::uploader', method_exists(Inputs\InputFileDragDrop::class, 'uploader'), true);
eq('InputRepeater::repeaterSortable', method_exists(Inputs\InputRepeater::class, 'repeaterSortable'), true);

# ---------------------------------------------------------------------------
# 3. lo schema prodotto è quello di prima del refactor
# ---------------------------------------------------------------------------
$number = FormField::key('p')->number()->decimal(3)->symbol('kg')->symbolPlacement('s')->decimals(2)->required();
eq('number context', $number->get('context'), ['number' => [
    'decimal' => 3, 'symbol' => 'kg', 'symbol_placement' => 's', 'decimals' => 2,
]]);
eq('number attribute', $number->get('attribute'), 'required');

eq('symbolPlacement ignora valori fuori dominio',
    FormField::key('p')->number()->symbolPlacement('x')->get('context'), []);

$password = FormField::key('p')->password()->minLength(8)->requireUppercase()->requireSpecial();
eq('password rules', $password->get('prepare'), ['password_rules' => [
    'min_length' => 8, 'uppercase' => true, 'special' => true,
]]);
eq('requireUppercase(false) rimuove la regola',
    FormField::key('p')->password()->requireUppercase()->requireUppercase(false)->get('prepare'),
    ['password_rules' => []]);

$select = FormField::key('s')->select(['1' => 'Uno'])->multiple()->old()->required();
eq('select options', $select->get('options'), ['1' => 'Uno']);
eq('select multiple', $select->get('multiple'), true);
eq('select version', $select->get('version'), 'old');
eq('select attribute', $select->get('attribute'), 'multiple required');

$file = FormField::key('f')->fileDragDrop('pdf', 'classic')->maxFile(3)->maxSize(9)->extensions('.PDF, doc|docx');
eq('file accept', $file->get('file'), 'pdf');
eq('file uploader', $file->get('uploader'), 'classic');
eq('file prepare', $file->get('prepare'), [
    'max_file' => 3, 'max_size' => 9, 'extensions' => ['pdf', 'doc', 'docx'],
]);

# ---------------------------------------------------------------------------
# 4. retro-compatibilità: modificatore chiamato PRIMA del type-helper
# ---------------------------------------------------------------------------
# Modificatori che il type-helper NON riceve come argomento: sopravvivono.
eq('decimals() prima di number()',
    FormField::key('p')->decimals(2)->number()->get('context'),
    ['number' => ['decimals' => 2]]);
eq('minLength() prima di password()',
    FormField::key('p')->minLength(8)->password()->get('prepare'),
    ['password_rules' => ['min_length' => 8]]);
eq('maxFile() prima di file()',
    FormField::key('f')->maxFile(4)->file('image')->get('prepare'),
    ['max_file' => 4]);
eq('extensions() prima di fileDragDrop()',
    FormField::key('f')->extensions('png')->fileDragDrop()->get('prepare'),
    ['extensions' => ['png']]);
eq('searchBar() prima di radio()',
    FormField::key('r')->searchBar()->radio(['1' => 'Uno'], true)->get('search_bar'),
    true);
eq('nested() prima di repeater()',
    FormField::key('r')->nested()->repeater([])->get('context'),
    ['nested' => true]);

# Modificatori che il type-helper riceve come argomento: l'argomento vince,
# perché il type-helper li applica *dopo* il morph col proprio default.
# Comportamento invariato dal refactor — verificato contro il codice pre-morph.
eq('options() prima di select() viene sovrascritto dal default del type-helper',
    FormField::key('s')->options(['1' => 'Uno'])->select()->get('options'), []);
eq('dateMin() prima di dateInput() viene sovrascritto dal default',
    FormField::key('d')->dateMin('2026-01-01')->dateInput()->get('date_min'), null);
eq('...ma passandolo al type-helper funziona',
    FormField::key('d')->dateInput('2026-01-01')->get('date_min'), '2026-01-01');

# La forma canonica e quella con lo shim convergono quando l'argomento non collide.
$before = FormField::key('s')->label('L')->select(['1' => 'Uno']);
$after = FormField::key('s')->select(['1' => 'Uno'])->label('L');
eq('label prima o dopo il type-helper → stesso schema', $before->get(), $after->get());
eq('label prima o dopo il type-helper → stessa classe', $before::class, $after::class);

# label/value/columnSpan sopravvivono al morph
$morphed = FormField::key('a')->label('Etichetta')->value('v')->columnSpan(6)->text();
eq('label sopravvive al morph', $morphed->get('label'), 'Etichetta');
eq('value sopravvive al morph', $morphed->get('value'), 'v');
eq('columnSpan sopravvive al morph', $morphed->columnSpan['default'], 6);
eq('columnSpan dichiarato sopravvive', $morphed->hasExplicitColumnSpan(), true);
eq('columnSpan non dichiarato non viene forzato',
    FormField::key('a')->text()->hasExplicitColumnSpan(), false);

# il morph non muta l'istanza di partenza
$facade = FormField::key('a');
$typed = $facade->number();
eq('la facade non viene mutata dal morph', $facade->get('helper'), 'text');
eq('l\'istanza tipizzata è nuova', $typed === $facade, false);
eq('il nome viene trasferito', $typed->name, 'a');

# ---------------------------------------------------------------------------
# 5. RepeaterColumn eredita il comportamento
# ---------------------------------------------------------------------------
eq('RepeaterColumn::key()->select()', RepeaterColumn::key('a')->select(['1' => 'Uno'])::class, Inputs\InputSelect::class);

# ---------------------------------------------------------------------------
# 6. la resa HTML è identica partendo dalla facade o dalla classe tipizzata
# ---------------------------------------------------------------------------
$strip = static fn (string $html): string => preg_replace('/_[a-z]{10}\b/', '_ID', $html) ?? $html;

foreach (['wonder', 'bootstrap'] as $theme) {
    eq("text identico via facade e classe [{$theme}]",
        $strip(FormField::key('a')->text()->required()->render($theme)),
        $strip(Inputs\InputText::key('a')->required()->render($theme)));

    eq("select identico via facade e classe [{$theme}]",
        $strip(FormField::key('a')->select(['1' => 'Uno'])->required()->render($theme)),
        $strip(Inputs\InputSelect::key('a')->options(['1' => 'Uno'])->required()->render($theme)));

    eq("number identico via facade e classe [{$theme}]",
        $strip(FormField::key('a')->number()->decimals(2)->render($theme)),
        $strip(Inputs\InputNumber::key('a')->decimals(2)->render($theme)));
}

# ---------------------------------------------------------------------------
# 7. ogni tipo compila da sé il proprio Element: niente factory, niente helper
# ---------------------------------------------------------------------------
$elements = [
    'text' => Elements\InputText::class,
    'hidden' => Elements\Hidden::class,
    'email' => Elements\InputEmail::class,
    'tel' => Elements\InputTel::class,
    'url' => Elements\InputUrl::class,
    'color' => Elements\InputColor::class,
    'number' => Elements\InputNumber::class,
    'price' => Elements\InputPrice::class,
    'percentige' => Elements\InputPercentige::class,
    'password' => Elements\InputPassword::class,
    'textarea' => Elements\Textarea::class,
    'textGenerator' => Elements\TextGenerator::class,
    'textDate' => Elements\Date::class,
    'textDatetime' => Elements\InputDatetime::class,
    'dateInput' => Elements\DatePicker::class,
    'dateRange' => Elements\DateRange::class,
    'timeInput' => Elements\InputTime::class,
    'select' => Elements\Select::class,
    'selectSearch' => Elements\Select::class,
    'textList' => Elements\TextList::class,
    'checkbox' => Elements\Checkbox::class,
    'checkTree' => Elements\CheckTree::class,
    'dynamicCheck' => Elements\DynamicCheck::class,
    'checkBoolean' => Elements\CheckBoolean::class,
    'googleAddress' => Elements\GoogleAddress::class,
    'inputRepeater' => Elements\Repeater::class,
    'recaptcha' => Elements\reCAPTCHA::class,
];

foreach ($elements as $helper => $element) {
    eq("compile() di {$helper} → " . substr(strrchr($element, '\\'), 1),
        (new FormField('a', $helper))->compile()::class, $element);
}

# i tipi con opzioni cambiano Element in base a ciò che dichiarano
eq('checkbox con opzioni → CheckGroup',
    Inputs\InputCheckbox::key('a')->options(['1' => 'Uno'])->compile()::class, Elements\CheckGroup::class);
eq('radio → CheckGroup',
    Inputs\InputRadio::key('a')->compile()::class, Elements\CheckGroup::class);
eq('textarea con version → TextareaEditor',
    Inputs\InputTextarea::key('a')->version('plus')->compile()::class, Elements\TextareaEditor::class);
eq('searchText → SearchRemote',
    Inputs\InputSearchText::key('a')->url('/x')->compile()::class, Elements\SearchRemote::class);
eq('file → File',
    Inputs\InputFile::key('a')->compile()::class, Elements\File::class);
eq('fileDragDrop → File',
    Inputs\InputFileDragDrop::key('a')->compile()::class, Elements\File::class);

# un campo senza nome non è renderizzabile
eq('nome vuoto → compile() null', Inputs\InputText::key('')->compile(), null);

# la configurazione del tipo finisce davvero sull'Element
eq('la policy password arriva all\'Element',
    Inputs\InputPassword::key('a')->minLength(8)->requireSpecial()->compile()->toArray()['password_rules'] ?? null,
    ['min_length' => 8, 'special' => true]);
eq('le opzioni arrivano all\'Element',
    Inputs\InputSelect::key('a')->options(['1' => 'Uno'])->compile()->toArray()['options'] ?? null, ['1' => 'Uno']);
eq('selectSearch marca l\'attributo della lib',
    Inputs\InputSelectSearch::key('a')->compile()->getAttr('data-wi-select-search'), 'true');
eq('i limiti di data arrivano all\'Element',
    Inputs\InputDate::key('a')->dateMin('2020-01-01')->compile()->getAttr('data-wi-min-date'), '2020-01-01');
eq('il date nativo usa l\'attributo HTML',
    Inputs\InputTextDate::key('a')->dateMax('2030-12-31')->compile()->getAttr('max'), '2030-12-31');
eq('lo step temporale arriva all\'Element',
    Inputs\InputTime::key('a')->timeStep(900)->compile()->getAttr('step'), 900);

# helper sconosciuto: la facade lo dice esplicitamente
$thrown = null;
try { (new FormField('a', 'inesistente'))->compile(); } catch (Throwable $e) { $thrown = $e::class; }
eq('helper sconosciuto solleva', $thrown, RuntimeException::class);

echo $fail === 0 ? "\nTUTTI I TEST OK\n" : "\n{$fail} TEST FALLITI\n";
exit($fail === 0 ? 0 : 1);
