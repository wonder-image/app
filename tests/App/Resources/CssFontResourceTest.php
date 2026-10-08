<?php
/** php tests/App/Resources/CssFontResourceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Models\Css\CssFont;
use Wonder\App\Resources\Css\CssFontResource;

$campo = static function (string $key) {
    foreach (CssFont::dataSchema() as $field) {
        if ($field->key === $key) {
            return $field;
        }
    }

    return null;
};

check('il nome si salva come lo si scrive: DM Sans resta DM Sans', fn () =>
    $campo('name')?->format('DM Sans') === 'DM Sans'
);

check('css_font ha uno slug, fuori dal form e dalle colonne della tabella', function () use ($campo) {
    $form = array_map(fn ($field) => $field->name ?? '', CssFontResource::formSchema());
    $colonne = array_map(fn ($column) => $column->name ?? '', CssFontResource::tableSchema());

    return $campo('slug') !== null && !in_array('slug', $form, true) && !in_array('slug', $colonne, true);
});

check('lo slug nasce dal nome alla creazione e poi non cambia', function () {
    $nuovo = CssFontResource::mutateRequestValues(['name' => 'DM Sans', 'slug' => 'altro'], 'store');
    $modifica = CssFontResource::mutateRequestValues(['name' => 'DM Sans 2', 'slug' => 'altro'], 'update', 'backend', ['id' => 3]);

    return $nuovo['slug'] === 'DM Sans' && !array_key_exists('slug', $modifica);
});

check('css_font si sincronizza tenendo gli id, perché le impostazioni li puntano', fn () =>
    CssFont::syncSchema()?->keepIds === true
);

summary();
