<?php
/** php tests/App/SanitizeEscapeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
// sanitize()/sanitizeEcho() e formToArray() senza il bootstrap completo.
require __DIR__ . '/../../app/function/string/common.php';
require __DIR__ . '/../../app/function/string/sanitize.php';
require __DIR__ . '/../../app/function/sql.php';

use Wonder\App\Model;
use Wonder\Backend\Table\Field;
use Wonder\Data\Fields\Text;

$GLOBALS['CHARACTERS'] = [];

/** Formati come li prepara il Model: `name` con sanitizeFirst(), `note` testo normale, `raw` sanitize(false). */
function escapeFields(): array
{
    return [
        'name' => ['input' => ['format' => Model::prepareFormatFromField(Text::key('name')->sanitizeFirst())]],
        'note' => ['input' => ['format' => Model::prepareFormatFromField(Text::key('note'))]],
        'raw' => ['input' => ['format' => Model::prepareFormatFromField(Text::key('raw')->sanitize(false))]],
    ];
}

/** Scrive come il backend: formToArray() con lo schema che arriva dal Model. */
function writeEscape(array $post): array
{
    $GLOBALS['ALERT'] = null;
    $GLOBALS['NAME'] = (object) ['folder' => ''];

    return formToArray('escape_test', $post, escapeFields());
}

/** Renderer della lista con i metadati colonna dello stesso schema. */
function listField(): Field
{
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'escape_test', 'connection' => null, 'database' => 'main',
        'field' => escapeFields(), 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) ['site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api'];
    $TEXT = (object) [
        'titleS' => 'riga', 'titleP' => 'righe', 'last' => 'ultime', 'all' => 'tutte',
        'article' => 'le', 'full' => 'piena', 'empty' => 'vuota', 'this' => 'questa',
    ];
    $USER = (object) ['area' => '', 'authority' => ''];
    $PAGE = (object) ['redirect' => '', 'redirectBase64' => ''];

    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

# ---------------------------------------------------------------------------
# Scrittura
# ---------------------------------------------------------------------------

check("sanitizeFirst(): l'apostrofo si escapa una volta sola", function () {
    return writeEscape(['name' => "cosa c'è dentro"])['name'] === "Cosa C\\'è Dentro";
});

check('sanitizeFirst(): minuscole e iniziali maiuscole restano', function () {
    return writeEscape(['name' => 'MAGLIA ROSSA'])['name'] === 'Maglia Rossa';
});

check("testo normale: l'apostrofo si escapa una volta", function () {
    return writeEscape(['note' => "l'olio"])['note'] === "l\\'olio";
});

check('sanitize(false): il valore non si tocca', function () {
    return writeEscape(['raw' => "l'olio"])['raw'] === "l'olio";
});

check('scrivere e rileggere con sanitizeEcho() ridà il testo di partenza', function () {
    return sanitizeEcho(writeEscape(['name' => "cosa c'è dentro"])['name']) === "Cosa C'è Dentro";
});

# ---------------------------------------------------------------------------
# Lista backend
# ---------------------------------------------------------------------------

check("lista: la barra di escape non si vede", function () {
    return listField()->newField(['id' => 1, 'name' => "Cosa C\\'è Dentro"], 'name', ['name' => 'name']) === "Cosa C'è Dentro";
});

check('lista: le entità restano codificate, niente HTML nuovo', function () {
    $html = listField()->newField(['id' => 1, 'note' => '&lt;b&gt;x&lt;/b&gt; l\\\'olio'], 'note', ['name' => 'note']);

    return $html === "&lt;b&gt;x&lt;/b&gt; l'olio";
});

check('lista: una colonna sanitize(false) resta com\'è', function () {
    return listField()->newField(['id' => 1, 'raw' => 'C:\\temp'], 'raw', ['name' => 'raw']) === 'C:\\temp';
});

check('lista: una colonna senza schema resta com\'è', function () {
    return listField()->newField(['id' => 1, 'altro' => "a\\'b"], 'altro', ['name' => 'altro']) === "a\\'b";
});

summary();
