<?php

use Wonder\App\Import\Importer;
use Wonder\App\Import\Mapping;
use Wonder\App\Import\Profile;
use Wonder\App\Import\Store;
use Wonder\App\Resources\Config\ImportResource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Alert, Badge, Button};
use Wonder\Elements\Form\Form;
use Wonder\Http\Csrf;
use Wonder\Http\Exceptions\ForbiddenHttpException;

// File → colonne → anteprima → scrittura a blocchi → annullamento: lo stato
// del lavoro (`?job=`) dice quale passo mostrare.
$KEY = (string) ($_GET['layout'] ?? '');

try {
    $LAYOUT = ImportResource::layout($KEY);
} catch (\Wonder\App\Import\ImportError $exception) {
    throw new ForbiddenHttpException($exception->getMessage());
}

$JOB = ImportResource::job($KEY, (int) ($_GET['job'] ?? 0));
$STATUS = (string) ($JOB['status'] ?? '');
$URL = ImportResource::url(['layout' => $KEY]);
$STATUSES = [
    'uploaded' => ['Da abbinare', 'secondary'],
    'validated' => ['Controllato', 'primary'],
    'running' => ['In corso', 'warning'],
    'done' => ['Fatto', 'success'],
    'failed' => ['Con errori', 'danger'],
    'undone' => ['Annullato', 'secondary'],
];
$ACTIONS = ['create' => 'Nuove', 'update' => 'Da aggiornare', 'unchanged' => 'Senza modifiche'];

$hidden = static fn (array $values): array => array_map(
    static fn (string $key, string $value) => FormField::key($key)->hidden()->value($value),
    array_keys($values),
    array_values($values),
);
$badge = static fn (string $status): string => Badge::make($STATUSES[$status][0] ?? $status)->variant($STATUSES[$status][1] ?? 'secondary')->render('bootstrap');
$list = static function (?string $json): array {
    $values = json_decode((string) $json, true);

    return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
};

\Wonder\View\View::layout('backend.show', [
    'TITLE' => 'Carica da file',
    'SUBTITLE' => $LAYOUT->label(),
]);
?>
<?php if ($LAYOUT->backUrl() !== null) { ?>
<div class="mb-3"><?= Button::make('Torna alla lista', $LAYOUT->backUrl())->variant('light')->size('sm')->render('bootstrap') ?></div>
<?php } ?>

<?php if ($JOB === null) {
    $profiles = Profile::options($KEY);
    $width = $profiles === [] ? 8 : 5;
    ?>
<div class="card border"><div class="card-body">
    <h6 class="mb-3">Scegli il file</h6>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden(['layout' => $KEY, 'action' => 'upload']),
        FormField::key('file')->file('spreadsheet')->label('File .xlsx o .csv')->columnSpan(['default' => 12, 'md' => $width]),
        ...($profiles === [] ? [] : [
            FormField::key('import_layout_id')->select(['' => 'Colonne del programma'] + $profiles, 'old')
                ->value((string) (Profile::defaultId($KEY) ?: ''))->label('Tracciato')->columnSpan(['default' => 12, 'md' => 3]),
        ]),
        Button::make('Carica')->variant('primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'import-upload', 'method' => 'post', 'action' => $URL, 'enctype' => 'multipart/form-data', 'footer' => Csrf::fieldFor('post')]) ?>
    <p class="small text-body-secondary mb-0 mt-3">La prima riga del file contiene le intestazioni. Niente cambia finché non avvii il caricamento dall'anteprima.</p>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <span class="small text-body-secondary">File di esempio, con tutte le colonne:</span>
        <?= Button::make('.xlsx', ImportResource::sampleUrl($KEY, 'xlsx'))->variant('light')->size('sm')->render('bootstrap') ?>
        <?= Button::make('.csv', ImportResource::sampleUrl($KEY, 'csv'))->variant('light')->size('sm')->render('bootstrap') ?>
    </div>
</div></div>
<?php } else {
    $jobId = (int) $JOB['id'];
    $jobUrl = ImportResource::url(['layout' => $KEY, 'job' => $jobId]);
    $counts = Store::rowCounts($jobId);
    ?>
<div class="card border"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-1"><?= e((string) $JOB['file_name']) ?> <?= $badge($STATUS) ?></h6>
            <p class="small text-body-secondary mb-0"><?= (int) $JOB['rows_total'] ?> righe nel file</p>
        </div>
        <?= Button::make('Carica un altro file', $URL)->variant('light')->size('sm')->render('bootstrap') ?>
    </div>
</div></div>

<?php if (in_array($STATUS, ['uploaded', 'validated'], true)) {
    $mapping = Importer::mapping($JOB);
    $options = ['' => '— non caricare —'];

    foreach (Profile::for($LAYOUT, (int) ($JOB['import_layout_id'] ?? 0) ?: null) ?? $LAYOUT->columns() as $column) {
        $options[$column->key] = $column->header;
    }

    foreach ($LAYOUT->columns() as $column) {
        $options[$column->key] ??= $column->header;
    }

    $report = Mapping::report($mapping['headers'], $mapping['columns'], $LAYOUT->columns());
    $fields = [];

    foreach ($mapping['headers'] as $index => $header) {
        $fields[] = FormField::key('col_'.$index)->select($options, 'old')->value((string) ($mapping['columns'][$index] ?? ''))
            ->label($header !== '' ? $header : 'Colonna '.($index + 1))->columnSpan(['default' => 12, 'md' => 6, 'lg' => 4]);
    }
    ?>
<div class="card border mt-3"><div class="card-body">
    <h6 class="mb-1">Colonne</h6>
    <p class="small text-body-secondary mb-3">Per ogni colonna del file scegli il dato che contiene. Le colonne lasciate su «non caricare» non cambiano niente.</p>
    <?php if ($report['missing'] !== []) { ?>
    <?= Alert::make('Mancano colonne obbligatorie: '.implode(', ', array_map(static fn ($column): string => '«'.($options[$column->key] ?? $column->header).'»', $report['missing'])).'. Aggiungile al file o abbinale qui sotto: le righe si controllano solo quando ci sono tutte.', 'warning')->inline()->addClass('mb-3')->render('bootstrap') ?>
    <?php } ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-top small mb-0">
            <thead><tr><th>Colonna del file</th><th>Dato del programma</th><th>Dove va</th></tr></thead>
            <tbody>
                <?php foreach ($report['recognized'] as $item) { ?>
                <tr>
                    <td><?= e($item['header'] !== '' ? $item['header'] : 'Colonna '.($item['index'] + 1)) ?></td>
                    <td><?= e($options[$item['column']->key] ?? $item['column']->header) ?><?= $item['column']->required ? ' '.Badge::make('obbligatoria')->variant('secondary')->render('bootstrap') : '' ?></td>
                    <td class="text-body-secondary"><?= e($item['column']->field ?? '') ?></td>
                </tr>
                <?php } ?>
                <?php foreach ($report['ignored'] as $item) { ?>
                <tr class="text-body-secondary">
                    <td><?= e($item['header'] !== '' ? $item['header'] : 'Colonna '.($item['index'] + 1)) ?></td>
                    <td colspan="2">Non riconosciuta: non si carica</td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?= Layout::render((new Form())->columns(12)->components([
        ...$hidden(['layout' => $KEY, 'job' => (string) $jobId, 'action' => 'map']),
        ...$fields,
        Button::make($STATUS === 'validated' ? 'Controlla di nuovo' : 'Controlla le righe')->variant($STATUS === 'validated' ? 'light' : 'primary')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
    ]), ['id' => 'import-map', 'method' => 'post', 'action' => $jobUrl, 'footer' => Csrf::fieldFor('post')]) ?>
</div></div>
<?php } ?>

<?php if ($STATUS !== 'uploaded') {
    $summary = [];

    foreach ($ACTIONS as $action => $label) {
        if (!empty($counts['action:'.$action])) {
            $summary[] = $label.': '.$counts['action:'.$action];
        }
    }

    if (!empty($counts['error'])) {
        $summary[] = 'Con errori: '.$counts['error'];
    }

    $problems = Store::rows($jobId, [], 200, true);
    ?>
<div class="card border mt-3"><div class="card-body">
    <h6 class="mb-1"><?= $STATUS === 'validated' ? 'Anteprima' : 'Risultato' ?></h6>
    <p class="small text-body-secondary mb-3"><?= e(implode(' · ', $summary) ?: 'Nessuna riga.') ?></p>

    <?php if ($STATUS === 'running') { ?>
    <div data-import-step="<?= e(ImportResource::stepUrl($jobId)) ?>" data-import-done="<?= e($jobUrl) ?>">
        <div class="progress mb-2" role="progressbar" aria-label="Avanzamento" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: 0%"></div>
        </div>
        <p class="small text-body-secondary mb-0" data-import-text>Scrittura in corso: lascia aperta la pagina.</p>
    </div>
    <script>
    (() => {
        const box = document.querySelector('[data-import-step]');
        const bar = box.querySelector('.progress-bar');
        const text = box.querySelector('[data-import-text]');
        const token = () => document.querySelector('meta[name="wi-csrf"]')?.content || '';
        const step = async () => {
            try {
                const response = await fetch(box.dataset.importStep, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-WI-CSRF': token() } });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || '');
                const percent = data.total > 0 ? Math.round(data.done * 100 / data.total) : 100;
                bar.style.width = percent + '%';
                box.querySelector('.progress').setAttribute('aria-valuenow', percent);
                text.textContent = data.done + ' di ' + data.total + ' righe scritte';
                if (data.status === 'running') { setTimeout(step, 200); } else { window.location.href = box.dataset.importDone; }
            } catch (error) {
                text.textContent = (error.message || 'Il collegamento si è interrotto.') + ' Ricarica la pagina per continuare.';
            }
        };
        step();
    })();
    </script>
    <?php } ?>

    <?php if ($problems !== []) { ?>
    <div class="table-responsive mt-3">
        <table class="table table-sm align-top small">
            <thead><tr><th>Riga</th><th>Azione</th><th>Errori e avvisi</th></tr></thead>
            <tbody>
                <?php foreach ($problems as $row) { ?>
                <tr>
                    <td><?= (int) $row['line'] ?></td>
                    <td><?= e($ACTIONS[(string) $row['action']] ?? (string) $row['action']) ?></td>
                    <td>
                        <?php foreach ($list($row['errors']) as $error) { ?><div class="text-danger"><?= e($error) ?></div><?php } ?>
                        <?php foreach ($list($row['warnings']) as $warning) { ?><div class="text-warning-emphasis"><?= e($warning) ?></div><?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($STATUS === 'validated' && (int) $JOB['rows_error'] === 0 && (int) $JOB['rows_valid'] > 0) { ?>
        <?= Button::post($jobUrl, 'Avvia il caricamento')->hidden(['layout' => $KEY, 'job' => $jobId, 'action' => 'start'])->variant('primary')->render('bootstrap') ?>
        <?php } elseif ($STATUS === 'validated' && (int) $JOB['rows_error'] > 0) { ?>
        <p class="small text-danger mb-0">Correggi le righe con errori nel file e caricalo di nuovo: il caricamento parte solo senza errori.</p>
        <?php } ?>
        <?php if (in_array($STATUS, ['done', 'failed'], true)) { ?>
        <?= Button::post($jobUrl, 'Annulla il caricamento')->hidden(['layout' => $KEY, 'job' => $jobId, 'action' => 'undo'])->variant('danger')
            ->confirm('Le righe create vengono eliminate e quelle modificate tornano com\'erano prima del caricamento.', title: 'Annullare il caricamento?', ok: 'Annulla il caricamento', variant: 'danger')->render('bootstrap') ?>
        <?php } ?>
    </div>
</div></div>
<?php } ?>
<?php } ?>

<?php $RECENT = Store::recentJobs($KEY, 10); ?>
<?php if ($RECENT !== []) { ?>
<div class="card border mt-3"><div class="card-body">
    <h6 class="mb-3">Ultimi caricamenti</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle small mb-0">
            <thead><tr><th>File</th><th>Data</th><th>Stato</th><th>Righe</th></tr></thead>
            <tbody>
                <?php foreach ($RECENT as $row) { ?>
                <tr>
                    <td><a href="<?= e(ImportResource::url(['layout' => $KEY, 'job' => (int) $row['id']])) ?>"><?= e((string) $row['file_name']) ?></a></td>
                    <td><?= e(date('d/m/Y H:i', strtotime((string) ($row['creation'] ?? 'now')) ?: time())) ?></td>
                    <td><?= $badge((string) $row['status']) ?></td>
                    <td><?= (int) $row['rows_created'] ?> nuove, <?= (int) $row['rows_updated'] ?> aggiornate</td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php } ?>
<?php \Wonder\View\View::end(); ?>
