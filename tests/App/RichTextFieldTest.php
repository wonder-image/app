<?php
/** php tests/App/RichTextFieldTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
// sanitize()/sanitizeEcho() e formToArray() senza il bootstrap completo.
require __DIR__ . '/../../app/function/string/common.php';
require __DIR__ . '/../../app/function/string/sanitize.php';
require __DIR__ . '/../../app/function/sql.php';

use Wonder\App\Model;
use Wonder\Sql\TableSchema as Column;
use Wonder\Data\Fields\Text;
use Wonder\Data\Formatters\String\RichTextFormatter;
use Wonder\Data\UploadSchema as Field;

/**
 * Model di test: una colonna testo formattato accanto a una colonna testo
 * normale, per vedere che scrittura e lettura trattano solo la prima come HTML.
 */
final class RichTextFieldModel extends Model
{
    public static string $table = 'rich_text_test';

    public static function tableSchema(): array
    {
        return [Column::key('description')->type('TEXT'), Column::key('note')->varchar()];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('description')->text()->richText(),
            Field::key('note')->text(),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function exposeNormalize(array $row): array
    {
        return static::normalizeReadRow($row);
    }

    /** @return array<int,string> */
    public static function exposeColumns(): array
    {
        return static::sanitizedReadColumns();
    }
}

$GLOBALS['CHARACTERS'] = [];

function richFormat(): array
{
    return Model::prepareFormatFromField(Text::key('description')->richText());
}

/** Scrive come il backend: formToArray() con lo schema che arriva dal Model. */
function writeRich(array $post, array $format = []): array
{
    $GLOBALS['ALERT'] = null;
    $GLOBALS['NAME'] = (object) ['folder' => ''];

    return formToArray('rich_text_test', $post, [
        'description' => ['input' => ['format' => $format === [] ? richFormat() : $format]],
        'note' => ['input' => ['format' => Model::prepareFormatFromField(Text::key('note'))]],
    ]);
}

# ---------------------------------------------------------------------------
# Dichiarazione
# ---------------------------------------------------------------------------

check('richText() segna il campo: rich_text e sanitize(false)', function () {
    $schema = Text::key('description')->richText()->getSchema();

    return ($schema['rich_text'] ?? null) === true && ($schema['sanitize'] ?? null) === false;
});

check('richText() aggiunge il formatter una volta sola anche se chiamato due volte', function () {
    $formatters = (array) Text::key('description')->richText()->richText()->getSchema('formatters');
    $rich = array_filter($formatters, fn ($formatter) => $formatter instanceof RichTextFormatter);

    return count($rich) === 1;
});

check('prepareFormatFromField: rich_text true e sanitize false', function () {
    $format = richFormat();

    return ($format['rich_text'] ?? null) === true
        && ($format['sanitize'] ?? null) === false
        && !isset($format['html_to_text']);
});

check('sanitize(true) dopo richText() non riaccende il sanitize', function () {
    $format = Model::prepareFormatFromField(Text::key('description')->richText()->sanitize(true));

    return ($format['sanitize'] ?? null) === false && ($format['rich_text'] ?? null) === true;
});

check('richText() spegne htmlToText()', function () {
    $format = Model::prepareFormatFromField(Text::key('description')->htmlToText()->richText());

    return !isset($format['html_to_text']) && ($format['rich_text'] ?? null) === true;
});

# ---------------------------------------------------------------------------
# Scrittura dal Model (Model::create/update → Field::format)
# ---------------------------------------------------------------------------

check('Field::format() pulisce l\'HTML', fn () => Text::key('description')->richText()
    ->format('  <p onclick="x()">ciao <strong>mondo</strong></p><script>alert(1)</script>  ') === '<p>ciao <strong>mondo</strong></p>');

check('Field::format(): editor vuoto → \'\'', fn () => Text::key('description')->richText()->format('<p><br></p>') === '');

check('Field::format(): null resta null', fn () => Text::key('description')->richText()->format(null) === null);

check('Model::prepare() pulisce la colonna rich e lascia la nota', function () {
    $prepared = RichTextFieldModel::prepare([
        'description' => '<p style="x">a <a href="javascript:alert(1)">b</a></p>',
        'note' => ' testo ',
    ]);

    return ($prepared['description'] ?? null) === '<p>a b</p>' && ($prepared['note'] ?? null) === 'testo';
});

# ---------------------------------------------------------------------------
# Scrittura dal backend (formToArray in app/function/sql.php)
# ---------------------------------------------------------------------------

check('formToArray pulisce l\'HTML del campo rich', function () {
    $values = writeRich(['description' => '<p class="c">Perché <em>sì</em></p><iframe src="x"></iframe>']);

    return ($values['description'] ?? null) === '<p>Perché <em>sì</em></p>';
});

check('formToArray: virgolette e apostrofi nel rich non prendono slash', function () {
    $values = writeRich(['description' => '<p>L\'estate "top"</p>']);

    return ($values['description'] ?? null) === '<p>L\'estate "top"</p>';
});

check('formToArray: la nota normale passa ancora da sanitize()', function () {
    $values = writeRich(['description' => '<p>x</p>', 'note' => "L'estate"]);

    return ($values['note'] ?? null) === "L\\'estate";
});

check('formToArray: editor vuoto → \'\'', function () {
    $values = writeRich(['description' => '<p><br></p>']);

    return array_key_exists('description', $values) && $values['description'] === '';
});

check('formToArray: un sanitize true arrivato dal form non tocca il rich', function () {
    $values = writeRich(
        ['description' => '<p>L\'estate &amp; <b>x</b></p>'],
        array_merge(richFormat(), ['sanitize' => true])
    );

    return ($values['description'] ?? null) === '<p>L\'estate &amp; <b>x</b></p>';
});

# ---------------------------------------------------------------------------
# Lettura (Model::normalizeReadRow)
# ---------------------------------------------------------------------------

check('la colonna rich è fuori dal set di sanitizeEcho, la nota dentro', function () {
    $columns = RichTextFieldModel::exposeColumns();

    return !in_array('description', $columns, true) && in_array('note', $columns, true);
});

check('in lettura il rich resta com\'è (niente decode di &lt; né stripslashes)', function () {
    $stored = '<p>3 &lt; 5 e L\'estate</p>';
    $row = RichTextFieldModel::exposeNormalize(['description' => $stored, 'note' => "L\\'estate"]);

    return $row['description'] === $stored && $row['note'] === "L'estate";
});

summary();
