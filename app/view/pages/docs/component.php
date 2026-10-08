<?php

/**
 * La scheda di un componente: descrizione, disponibilità per tema, esempi con
 * anteprima e codice, riferimento API. Variabili da
 * `Wonder\Docs\CatalogPage::component()`.
 *
 * @var \Wonder\Docs\Urls $urls
 * @var string[] $themes
 * @var array<string, \Wonder\Docs\Category> $categories
 * @var \Wonder\Docs\ComponentDoc $doc
 * @var array<string, \Wonder\Docs\ThemeAvailability> $availability
 * @var array<int, array{index: int, example: \Wonder\Docs\Example, code: string}> $examples
 * @var array{constructor: ?array<string, mixed>, own: array<int, array<string, mixed>>, inherited: array<string, array<int, array<string, mixed>>>} $api
 * @var array{previous: ?\Wonder\Docs\ComponentDoc, next: ?\Wonder\Docs\ComponentDoc} $neighbors
 * @var \Wonder\Docs\ComponentDoc[] $related
 */

use Wonder\Docs\Pages;
use Wonder\Docs\ThemeSupport;

$category = $categories[$doc->getCategory()] ?? null;
$groupTitle = $category?->groupTitle($doc->getGroup()) ?? '';

$signature = static function (array $method): string {
    return ($method['static'] ? 'static ' : '').$method['name'].'('.$method['parameters'].')'.($method['returns'] !== '' ? ': '.$method['returns'] : '');
};

$methodRow = static function (array $method) use ($signature): string {
    return '<tr'.($method['deprecated'] ? ' class="text-body-tertiary"' : '').'>'
        .'<td><code>'.e($signature($method)).'</code>'.($method['deprecated'] ? ' <span class="badge text-bg-warning">deprecato</span>' : '').'</td>'
        .'<td class="text-body-secondary">'.Pages::text($method['summary']).'</td>'
        .'</tr>';
};

?>
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e($urls->index()) ?>" class="text-decoration-none">Catalogo</a></li>
        <?php if ($category !== null) : ?>
            <li class="breadcrumb-item"><a href="<?= e($urls->index()) ?>#<?= e($category->key) ?>" class="text-decoration-none"><?= e($category->title) ?></a></li>
        <?php endif; ?>
        <?php if ($groupTitle !== '') : ?>
            <li class="breadcrumb-item"><?= e($groupTitle) ?></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= e($doc->getTitle()) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1 d-flex align-items-center gap-2 flex-wrap">
            <?= e($doc->getTitle()) ?>
            <span class="fs-6 fw-normal"><?= Pages::badges($doc, $availability) ?></span>
        </h1>
        <div class="small text-body-tertiary"><code><?= e($doc->getClass()) ?></code></div>
    </div>
    <?= Pages::themeSwitch($themes) ?>
</div>

<?php if ($doc->getDeprecated() !== null) : ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> <strong>Deprecato.</strong> <?= Pages::text($doc->getDeprecated()) ?></div>
<?php endif; ?>

<?php if ($doc->getDescription() !== '') : ?>
    <div class="fs-6 mb-3"><?= Pages::text($doc->getDescription(), true) ?></div>
<?php endif; ?>

<?php $notes = array_filter($doc->getNotes()); ?>
<?php if ($notes !== [] || array_filter($availability, static fn ($support) => !$support->available || $support->inherited) !== []) : ?>
    <div class="row g-2 mb-4">
        <?php foreach ($availability as $theme => $support) : $note = $doc->getNote($theme); ?>
            <?php if ($note === '' && $support->available && !$support->inherited) { continue; } ?>
            <div class="col-12 col-md-6">
                <div class="border rounded p-3 h-100 small <?= $support->available ? '' : 'bg-body-tertiary' ?>">
                    <div class="fw-semibold mb-1">
                        <i class="bi <?= $support->available ? 'bi-check-circle text-success' : 'bi-dash-circle text-body-tertiary' ?>" aria-hidden="true"></i>
                        <?= e($support->label()) ?>
                    </div>
                    <?php if (!$support->available || $support->inherited) : ?><div class="text-body-secondary"><?= Pages::text($support->reason) ?></div><?php endif; ?>
                    <?php if ($note !== '') : ?><div><?= Pages::text($note) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($doc->getDocs() !== []) : ?>
    <p class="small mb-4">
        <i class="bi bi-book me-1" aria-hidden="true"></i>
        <?php foreach ($doc->getDocs() as $position => $link) : ?>
            <?= $position > 0 ? ' · ' : '' ?><a href="<?= e($urls->guide($link['href'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if ($examples === []) : ?>
    <div class="alert alert-light border">Questa scheda non ha ancora esempi.</div>
<?php endif; ?>

<?php foreach ($examples as $entry) : $example = $entry['example']; ?>
    <section class="wi-docs-example mb-5" id="esempio-<?= e((string) ($entry['index'] + 1)) ?>">
        <h2 class="h5 mb-1"><?= e($example->getTitle()) ?></h2>
        <?php if ($example->getDescription() !== '') : ?>
            <div class="text-body-secondary mb-2"><?= Pages::text($example->getDescription(), true) ?></div>
        <?php endif; ?>
        <?= Pages::preview($doc, $entry['index'], $example, $availability, $urls)->render('bootstrap') ?>
        <div class="mt-2"><?= Pages::code($entry['code'])->render('bootstrap') ?></div>
    </section>
<?php endforeach; ?>

<section class="wi-docs-api mb-5" id="api">
    <h2 class="h5 mb-2">API</h2>
    <p class="small text-body-secondary">Metodi pubblici letti dalla classe; getter e <code>render()</code> esclusi. Tutti i setter ritornano l'istanza, quindi si concatenano.</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle wi-docs-table">
            <thead><tr><th scope="col" style="width: 55%">Metodo</th><th scope="col">Cosa fa</th></tr></thead>
            <tbody>
                <?php if ($api['constructor'] !== null) : ?>
                    <tr><td><code>new <?= e($doc->shortName()) ?>(<?= e($api['constructor']['parameters']) ?>)</code></td><td class="text-body-secondary"><?= Pages::text($api['constructor']['summary']) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($api['own'] as $method) : ?><?= $methodRow($method) ?><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($api['inherited'] !== []) : ?>
        <details class="mt-2">
            <summary class="small text-body-secondary">Metodi ereditati da classi madri e concern</summary>
            <?php foreach ($api['inherited'] as $origin => $methods) : ?>
                <h3 class="h6 mt-3 mb-1">Da <code><?= e($origin) ?></code></h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle wi-docs-table mb-0">
                        <tbody><?php foreach ($methods as $method) : ?><?= $methodRow($method) ?><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </details>
    <?php endif; ?>
</section>

<?php if ($related !== []) : ?>
    <section class="mb-4">
        <h2 class="h6 text-body-secondary">Vedi anche</h2>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($related as $item) : ?>
                <a href="<?= e($urls->component($item->getSlug())) ?>" class="btn btn-sm btn-outline-secondary"><?= e($item->getTitle()) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="d-flex justify-content-between gap-3 border-top pt-3 small">
    <div>
        <?php if ($neighbors['previous'] !== null) : ?>
            <a href="<?= e($urls->component($neighbors['previous']->getSlug())) ?>" class="text-decoration-none"><i class="bi bi-arrow-left-short" aria-hidden="true"></i> <?= e($neighbors['previous']->getTitle()) ?></a>
        <?php endif; ?>
    </div>
    <div class="text-end">
        <?php if ($neighbors['next'] !== null) : ?>
            <a href="<?= e($urls->component($neighbors['next']->getSlug())) ?>" class="text-decoration-none"><?= e($neighbors['next']->getTitle()) ?> <i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>
        <?php endif; ?>
    </div>
</div>
