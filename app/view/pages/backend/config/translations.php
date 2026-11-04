<?php

use Wonder\App\Resources\Config\TranslationsResource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Alert, Badge, Button, Steps};
use Wonder\Elements\Form\Form;
use Wonder\Localization\Catalog\Catalog;
use Wonder\Localization\Catalog\ImportPreview;

// Tre passi, uno per schermata: 1. cosa tradurre (GET), 2. scarica il file e
// carica quello tradotto, 3. anteprima sul server (token) e conferma.
$SELECTION = TranslationsResource::selection($_GET);
$LANGS = TranslationsResource::languages();
$SOURCES = array_map(static fn ($source): string => $source->label(), Catalog::all());
$TOKEN = (string) ($_GET['preview'] ?? '');
$PREVIEW = $TOKEN !== '' ? TranslationsResource::store()->load($TOKEN) : null;

if ($PREVIEW !== null && array_intersect_key($PREVIEW, $SELECTION) != $SELECTION) {
    $PREVIEW = null;
}

$STEP = match (true) {
    count($LANGS) < 2 => 0,
    $PREVIEW !== null => 3,
    isset($_GET['source']) && ($_GET['step'] ?? '') !== '1' && $SELECTION['from'] !== $SELECTION['to'] => 2,
    default => 1,
};

\Wonder\View\View::layout('backend.show', [
    'TITLE' => 'Traduzioni',
    'SUBTITLE' => 'Manda i testi al traduttore e carica il file che ti rimanda',
]);

$hidden = static fn (array $values): array => array_map(
    static fn (string $key, string $value) => FormField::key($key)->hidden()->value($value),
    array_keys($values),
    array_values($values),
);
$choice = ($SOURCES[$SELECTION['source']] ?? $SELECTION['source']).' · '
    .($LANGS[$SELECTION['from']] ?? $SELECTION['from']).' → '.($LANGS[$SELECTION['to']] ?? $SELECTION['to']);
$steps = Steps::make('Passi dell\'import');

foreach (['Cosa tradurre', 'Scarica e carica', 'Controlla e applica'] as $i => $label) {
    $n = $i + 1;
    $href = match ($n) { 1 => TranslationsResource::url($SELECTION + ['step' => 1]), 2 => TranslationsResource::url($SELECTION), default => null };
    $steps->step("{$n}. {$label}", $href, $n < $STEP ? 'done' : ($n === $STEP ? 'current' : 'todo'));
}
?>
<?php if ($STEP === 0) { ?>
<?= Alert::make('Il sito ha una sola lingua: aggiungi una seconda lingua per poter tradurre i testi.', 'info')->title('Nessuna lingua da tradurre')->inline()->dismissible(false)->render('bootstrap') ?>
<?php } else { ?>
<div class="mb-3"><?= $steps->render('bootstrap') ?></div>
<?php } ?>
<?php if ($STEP === 0) { } elseif ($STEP === 1) { ?>
<div class="card border"><div class="card-body">
    <h6 class="mb-3">Cosa vuoi tradurre?</h6>
    <?= Layout::render((new Form())->columns(12)->components([
        FormField::key('source')->select($SOURCES, 'old')->value($SELECTION['source'])->label('Testi')->columnSpan(['default' => 12, 'md' => 4]),
        FormField::key('from')->select($LANGS, 'old')->value($SELECTION['from'])->label('Dalla lingua')->columnSpan(['default' => 12, 'md' => 4]),
        FormField::key('to')->select($LANGS, 'old')->value($SELECTION['to'])->label('Alla lingua')->columnSpan(['default' => 12, 'md' => 4]),
        Button::make('Avanti')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4, 'lg' => 2]),
    ]), ['id' => 'translations-selection', 'method' => 'get']) ?>
</div></div>
<?php } elseif ($STEP === 2) { ?>
<div class="card border"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><strong><?= e($choice) ?></strong></div>
        <a class="small" href="<?= e(TranslationsResource::url($SELECTION + ['step' => 1])) ?>">Cambia</a>
    </div>
    <h6 class="mb-2">Manda il file al traduttore</h6>
    <p class="small text-body-secondary mb-2">Il traduttore scrive le traduzioni solo nell'ultima colonna (<?= e($LANGS[$SELECTION['to']] ?? $SELECTION['to']) ?>) e non tocca le altre.</p>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?= Button::make('Scarica il file Excel', TranslationsResource::exportUrl($SELECTION + ['format' => 'xlsx']))->variant('primary')->render('bootstrap') ?>
        <?= Button::make('Scarica in CSV', TranslationsResource::exportUrl($SELECTION + ['format' => 'csv']))->variant('light')->render('bootstrap') ?>
    </div>
    <h6 class="mb-2">Carica il file tradotto</h6>
    <p class="small text-body-secondary mb-2">Prima di salvare vedi cosa cambia. Le righe vuote o mancanti restano come sono.</p>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden($SELECTION + ['action' => 'upload']),
        FormField::key('sheet')->file('spreadsheet')->label('File .xlsx o .csv')->columnSpan(['default' => 12, 'md' => 8]),
        Button::make('Controlla il file')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'translations-upload', 'method' => 'post', 'action' => TranslationsResource::url($SELECTION), 'enctype' => 'multipart/form-data']) ?>
</div></div>
<?php
} else {
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
<div class="card border"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h6 class="mb-0">Cosa cambia con <?= e((string) ($PREVIEW['file'] ?? 'il file')) ?></h6>
        <a class="small" href="<?= e(TranslationsResource::url($SELECTION)) ?>">Carica un altro file</a>
    </div>
    <p class="small mb-1"><?= e($choice) ?></p>
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
    <p class="small text-body-secondary mb-2">Scegli le righe da salvare: quelle con «sorgente cambiata» partono deselezionate.</p>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden($SELECTION + ['action' => 'confirm', 'token' => $TOKEN]),
        FormField::key('ids')->checkbox()->options($options)->value($selected)->searchBar()
            ->label('Righe da salvare')->columnSpan(12),
        Button::make('Salva le traduzioni scelte')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'translations-confirm', 'method' => 'post', 'action' => TranslationsResource::url($SELECTION)]) ?>
    <?php } ?>
</div></div>
<?php } ?>
<?php \Wonder\View\View::end(); ?>
