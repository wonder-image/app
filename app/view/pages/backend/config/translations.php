<?php

use Wonder\App\Resources\Config\TranslationsResource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Badge, Button};
use Wonder\Elements\Form\Form;
use Wonder\Localization\Catalog\Catalog;
use Wonder\Localization\Catalog\ImportPreview;

// Export del catalogo per il traduttore e import in due passi: il file
// caricato diventa un'anteprima sul server (token), poi si confermano le righe.
$SELECTION = TranslationsResource::selection($_GET);
$LANGS = TranslationsResource::languages();
$SOURCES = array_map(static fn ($source): string => $source->label(), Catalog::all());
$TOKEN = (string) ($_GET['preview'] ?? '');
$PREVIEW = $TOKEN !== '' ? TranslationsResource::store()->load($TOKEN) : null;

if ($PREVIEW !== null && array_intersect_key($PREVIEW, $SELECTION) != $SELECTION) {
    $PREVIEW = null;
}

\Wonder\View\View::layout('backend.show', [
    'TITLE' => 'Traduzioni',
    'SUBTITLE' => 'Esporta i testi per il traduttore e importa il foglio tradotto',
]);

$third = ['default' => 12, 'md' => 4];
$hidden = static fn (array $values): array => array_map(
    static fn (string $key, string $value) => FormField::key($key)->hidden()->value($value),
    array_keys($values),
    array_values($values),
);
?>
<div class="card border"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Testi da tradurre</h6>
        <div class="d-flex flex-wrap gap-2">
            <?= Button::make('Scarica XLSX', TranslationsResource::exportUrl($SELECTION + ['format' => 'xlsx']))->variant('primary')->size('sm')->render('bootstrap') ?>
            <?= Button::make('Scarica CSV', TranslationsResource::exportUrl($SELECTION + ['format' => 'csv']))->variant('light')->size('sm')->render('bootstrap') ?>
        </div>
    </div>
    <?= Layout::render((new Form())->columns(12)->components([
        FormField::key('source')->select($SOURCES, 'old')->value($SELECTION['source'])->label('Sorgente')->columnSpan($third),
        FormField::key('from')->select($LANGS, 'old')->value($SELECTION['from'])->label('Lingua sorgente')->columnSpan($third),
        FormField::key('to')->select($LANGS, 'old')->value($SELECTION['to'])->label('Lingua destinazione')->columnSpan($third),
        Button::make('Aggiorna')->variant('light')->type('submit')->columnSpan(['default' => 12, 'md' => 4, 'lg' => 2]),
    ]), ['id' => 'translations-selection', 'method' => 'get']) ?>
    <p class="small text-body-secondary mb-0 mt-3">Il foglio ha le colonne id, contesto, <?= e($SELECTION['from']) ?> e <?= e($SELECTION['to']) ?>. Il traduttore compila solo l'ultima: una cella vuota lascia la traduzione com'è.</p>
</div></div>

<div class="card border mt-3"><div class="card-body">
    <h6 class="mb-3">Importa il foglio tradotto</h6>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden($SELECTION + ['action' => 'upload']),
        FormField::key('sheet')->file('spreadsheet')->label('File .xlsx o .csv')->columnSpan(['default' => 12, 'md' => 8]),
        Button::make('Mostra anteprima')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'translations-upload', 'method' => 'post', 'action' => TranslationsResource::url($SELECTION), 'enctype' => 'multipart/form-data']) ?>
    <p class="small text-body-secondary mb-0 mt-3">Nessuna traduzione cambia finché non confermi l'anteprima. Le chiavi che mancano nel file restano come sono.</p>
</div></div>
<?php
if ($PREVIEW !== null) {
    $rows = (array) ($PREVIEW['preview'] ?? []);
    $counts = array_count_values(array_column($rows, 'status'));
    $summary = [];
    $options = [];
    $selected = [];
    $variants = [
        ImportPreview::NEW => 'success',
        ImportPreview::MODIFIED => 'primary',
        ImportPreview::UNCHANGED => 'secondary',
        ImportPreview::SOURCE_CHANGED => 'warning',
        ImportPreview::ERROR => 'danger',
    ];

    foreach (ImportPreview::LABELS as $status => $label) {
        if (!empty($counts[$status])) {
            $summary[] = $label.': '.$counts[$status];
        }
    }

    foreach ($rows as $row) {
        if (ImportPreview::selectable($row)) {
            $options[(string) $row['id']] = (string) $row['id'];

            if (ImportPreview::selectedByDefault($row)) {
                $selected[] = (string) $row['id'];
            }
        }
    }
    ?>
<div class="card border mt-3"><div class="card-body">
    <h6 class="mb-1">Anteprima di <?= e((string) ($PREVIEW['file'] ?? 'file')) ?></h6>
    <p class="small text-body-secondary mb-3"><?= e(implode(' · ', $summary) ?: 'Il file non contiene traduzioni.') ?></p>
    <div class="table-responsive">
        <table class="table table-sm align-top small">
            <thead>
                <tr>
                    <th>Stato</th>
                    <th>Id</th>
                    <th><?= e($SELECTION['from']) ?></th>
                    <th><?= e($SELECTION['to']) ?> oggi</th>
                    <th><?= e($SELECTION['to']) ?> nel file</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?= Badge::make(ImportPreview::LABELS[$row['status']] ?? $row['status'])->variant($variants[$row['status']] ?? 'secondary')->render('bootstrap') ?></td>
                    <td><code><?= e($row['id']) ?></code><div class="text-body-secondary"><?= e($row['context']) ?></div></td>
                    <td><?= e_br($row['source']) ?><?php if ($row['status'] === ImportPreview::SOURCE_CHANGED) { ?><div class="text-warning-emphasis mt-1">Nel file: <?= e_br($row['file_source']) ?></div><?php } ?></td>
                    <td><?= e_br($row['current']) ?></td>
                    <td><?= e_br($row['value']) ?><?php foreach ((array) $row['errors'] as $error) { ?><div class="text-danger mt-1"><?= e($error) ?></div><?php } ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($options === []) { ?>
    <p class="small text-body-secondary mb-0">Nessuna riga da applicare.</p>
    <?php } else { ?>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden($SELECTION + ['action' => 'confirm', 'token' => $TOKEN]),
        FormField::key('ids')->checkbox()->options($options)->value($selected)->searchBar()
            ->label('Righe da applicare (le "sorgente cambiata" partono deselezionate)'),
        Button::make('Applica le traduzioni scelte')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'translations-confirm', 'method' => 'post', 'action' => TranslationsResource::url($SELECTION)]) ?>
    <?php } ?>
</div></div>
<?php } ?>
<?php \Wonder\View\View::end(); ?>
