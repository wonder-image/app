<?php
/** php tests/Themes/RepeaterGroupFilesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\Support\Repeater as RepeaterSupport;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

$righe = [
    'row_1' => ['option' => 'S', 'group' => 'Blu', 'group_value' => '10'],
    'row_2' => ['option' => 'M', 'group' => 'Blu', 'group_value' => '10'],
    'row_3' => ['option' => 'S', 'group' => 'Rosso', 'group_value' => '11'],
];

$foto = static fn (): Input => FormField::key('group_images')
    ->fileDragDrop('gallery')
    ->maxFile(10)
    ->value(['10' => ['blu-1.jpg', 'blu-2.jpg'], '11' => []]);

/** @param array<string, mixed> $context */
$render = static function (array $context) use ($righe): string {
    $field = new class($righe, $context) {
        public array $schema;

        public function __construct(array $value, array $context)
        {
            $this->schema = [
                'id' => 'products',
                'name' => 'products',
                'label' => '',
                'value' => $value,
                'columns' => [
                    RepeaterColumn::key('option')->text()->label('Opzione')->columnSpan(6),
                    RepeaterColumn::key('group')->hidden(),
                    RepeaterColumn::key('group_value')->hidden(),
                ],
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));

check('la testata ha il bottone con l\'etichetta e il pannello del campo', function () use ($render, $markup, $foto) {
    $html = $markup($render([
        'group_fixed' => 'group',
        'group_files' => ['field' => $foto(), 'key_column' => 'group_value', 'label' => 'Foto del colore'],
    ]));

    return str_contains($html, 'wi-repeater-group-files-toggle')
        && str_contains($html, 'Foto del colore (<span class="wi-repeater-group-files-count">0</span>)')
        && str_contains($html, 'class="card-body pt-0 d-none wi-repeater-group-files"')
        && str_contains($html, 'name="group_images[__GROUP_KEY__][]"');
});

check('il template porta la colonna della chiave e i file di ogni gruppo', function () use ($render, $markup, $foto) {
    $html = $markup($render([
        'group_fixed' => 'group',
        'group_files' => ['field' => $foto(), 'key_column' => 'group_value'],
    ]));

    return str_contains($html, 'data-wi-group-files-key="group_value"')
        && str_contains($html, 'data-wi-group-files-values="{&quot;10&quot;:[&quot;blu-1.jpg&quot;,&quot;blu-2.jpg&quot;],&quot;11&quot;:[]}"');
});

check('il campo nel template parte senza file: glieli dà la testata', function () use ($render, $markup, $foto) {
    $html = $markup($render([
        'group_fixed' => 'group',
        'group_files' => ['field' => $foto(), 'key_column' => 'group_value'],
    ]));

    return !str_contains($html, 'data-wi-value="[&quot;blu-1.jpg');
});

check('il bottone e il comando stanno insieme a destra', function () use ($render, $markup, $foto) {
    $html = $markup($render([
        'group_fixed' => 'group',
        'group_command' => ['column' => 'option', 'label' => 'Prezzo del gruppo'],
        'group_files' => ['field' => $foto(), 'key_column' => 'group_value'],
    ]));

    return (bool) preg_match('/<div class="ms-auto d-flex align-items-center gap-2"><button[^>]*wi-repeater-group-files-toggle.*?<div class="wi-repeater-group-command"/s', $html);
});

check('senza raggruppamento fisso i file non si stampano', function () use ($render, $markup, $foto) {
    $html = $markup($render([
        'group_by' => ['group'],
        'group_files' => ['field' => $foto(), 'key_column' => 'group_value'],
    ]));

    return str_contains($html, 'wi-repeater-group-header')
        && !str_contains($html, 'wi-repeater-group-files')
        && !str_contains($html, 'data-wi-group-files-key');
});

check('senza file la testata resta com\'era', function () use ($render, $markup) {
    $html = $markup($render(['group_fixed' => 'group']));

    return !str_contains($html, 'wi-repeater-group-files')
        && !str_contains($html, 'ms-auto d-flex');
});

check('l\'API salva il campo, la colonna e l\'etichetta', function () use ($foto) {
    $repeater = FormField::key('products')->repeater([])->repeaterGroupFiles($foto(), ' group_value ', 'Foto del colore');
    $files = $repeater->get('context')['group_files'] ?? [];

    return ($files['key_column'] ?? '') === 'group_value'
        && ($files['label'] ?? '') === 'Foto del colore'
        && ($files['field'] ?? null) instanceof Input;
});

check('dalla richiesta: manifesto e busta per gruppo', function () {
    $post = ['group_images' => [
        '10__wi_files' => '["blu-1.jpg",0]',
        '11__wi_files' => '[]',
    ]];
    $files = ['group_images' => [
        'name' => ['10' => ['nuova.jpg']],
        'type' => ['10' => ['image/jpeg']],
        'tmp_name' => ['10' => ['/tmp/php1']],
        'error' => ['10' => [0]],
        'size' => ['10' => [123]],
    ]];
    $groups = RepeaterSupport::groupFilesFromRequest('group_images', $post, $files);

    return count($groups) === 2
        && $groups['10']['manifest'] === ['blu-1.jpg', 0]
        && $groups['10']['files']['name'] === ['nuova.jpg']
        && $groups['10']['files']['tmp_name'] === ['/tmp/php1']
        && $groups['11']['manifest'] === []
        && $groups['11']['files'] === null;
});

check('un gruppo senza manifesto non compare: non era in pagina', function () {
    $groups = RepeaterSupport::groupFilesFromRequest('group_images', ['group_images' => ['10' => ['x.jpg']]], []);

    return $groups === [];
});

check('un manifesto illeggibile si salta', function () {
    return RepeaterSupport::groupFilesFromRequest('group_images', ['group_images' => ['10__wi_files' => '{rotto']], []) === [];
});

summary();
