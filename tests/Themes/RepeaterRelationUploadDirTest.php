<?php
/** php tests/Themes/RepeaterRelationUploadDirTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Model;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\Data\UploadSchema as Field;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Una colonna file di un repeater con relazione cerca il file già caricato
 * nella sottocartella che il Model correlato dichiara con `dir()`: è lì che
 * il salvataggio lo scrive. Senza, la preview va in 404.
 */
final class RepeaterUploadDirValue extends Model
{
    public static string $table = 'test_repeater_upload_values';
    public static string $folder = 'test/values';

    public static function tableSchema(): array
    {
        return [];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('label')->text(),
            Field::key('image')->image()->maxFile(1)->dir('/catalogo/attributi/'),
        ];
    }
}

$GLOBALS['PATH'] = (object) ['upload' => 'https://sito.test/assets/upload'];
$GLOBALS['NAME'] = (object) ['folder' => 'test/values'];

/** @param array<int, mixed> $columns */
$render = static function (array $columns, ?object $relation) : string {
    $field = new class($columns, $relation) {
        public array $schema;

        public function __construct(array $columns, ?object $relation)
        {
            $this->schema = [
                'id' => 'values',
                'name' => 'values',
                'label' => '',
                'value' => ['row_1' => ['label' => 'Vegan', 'image' => '["vegan.png"]']],
                'columns' => $columns,
                'context' => array_filter(['nested' => true, 'relation' => $relation]),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$relation = RepeaterRelation::make(RepeaterUploadDirValue::$table, 'attribute_id')->model(RepeaterUploadDirValue::class);

check('la colonna file prende la dir del Model della relazione', function () use ($render, $relation) {
    $html = $render([RepeaterColumn::key('image')->fileDragDrop('image')], $relation);

    return str_contains($html, 'data-wi-dir="https://sito.test/assets/upload/test/values/catalogo/attributi/"');
});

check('una dir dichiarata sulla colonna vince', function () use ($render, $relation) {
    $html = $render([RepeaterColumn::key('image')->fileDragDrop('image')->prepare('dir', '/altro/')], $relation);

    return str_contains($html, 'data-wi-dir="https://sito.test/assets/upload/test/values/altro/"');
});

check('senza relazione resta la cartella del contenuto', function () use ($render) {
    $html = $render([RepeaterColumn::key('image')->fileDragDrop('image')], null);

    return str_contains($html, 'data-wi-dir="https://sito.test/assets/upload/test/values/"');
});

summary();
