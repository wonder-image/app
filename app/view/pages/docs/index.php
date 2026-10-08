<?php

/**
 * L'indice del catalogo: panoramica dei componenti per categoria e gruppo,
 * con la disponibilità in ogni tema. Variabili da `Wonder\Docs\CatalogPage::index()`.
 *
 * @var \Wonder\Docs\Urls $urls
 * @var string[] $themes
 * @var array<string, \Wonder\Docs\Category> $categories
 * @var array<string, array<string, \Wonder\Docs\ComponentDoc[]>> $grouped
 * @var array<string, array<string, \Wonder\Docs\ThemeAvailability>> $catalogAvailability
 * @var array<string, int> $counts
 */

use Wonder\Docs\Pages;
use Wonder\Docs\ThemeSupport;

?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Catalogo dei componenti</h1>
        <p class="text-body-secondary mb-0">
            Ogni Element di <code>wonder-image/app</code> con il codice da copiare e l'anteprima resa dal tema scelto.
            Le anteprime Bootstrap si vedono anche in modalità scura.
        </p>
    </div>
    <?= Pages::themeSwitch($themes) ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border h-100"><div class="card-body py-3">
            <div class="small text-body-secondary">Componenti</div>
            <div class="h4 mb-0"><?= e((string) $counts['total']) ?></div>
        </div></div>
    </div>
    <?php foreach ($themes as $theme) : ?>
        <div class="col-6 col-md-3">
            <div class="card border h-100"><div class="card-body py-3">
                <div class="small text-body-secondary">Tema <?= e(ThemeSupport::label($theme)) ?></div>
                <div class="h4 mb-0"><?= e((string) ($counts[$theme] ?? 0)) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3">
        <div class="card border h-100"><div class="card-body py-3">
            <div class="small text-body-secondary">In tutti i temi</div>
            <div class="h4 mb-0"><?= e((string) $counts['both']) ?></div>
        </div></div>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    La disponibilità non è scritta a mano: per ogni componente il catalogo chiede al <code>Themes\Resolver</code>
    se esiste il renderer del tema, lo stesso che usa <code>render($theme)</code>.
    <span class="text-info"><i class="bi bi-check-lg" aria-hidden="true"></i> ereditato</span> indica un renderer preso da una classe madre.
</div>

<?php foreach ($grouped as $categoryKey => $groups) : $category = $categories[$categoryKey] ?? null; ?>
    <section class="mb-5" id="<?= e($categoryKey) ?>">
        <h2 class="h4 d-flex align-items-center gap-2 mb-1">
            <?php if ($category !== null && $category->icon !== '') : ?><i class="bi <?= e($category->icon) ?> text-body-secondary" aria-hidden="true"></i><?php endif; ?>
            <?= e($category?->title ?? $categoryKey) ?>
        </h2>
        <?php if ($category !== null && $category->description !== '') : ?>
            <p class="text-body-secondary"><?= Pages::text($category->description) ?></p>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle wi-docs-table mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 22%">Componente</th>
                        <th scope="col">Descrizione</th>
                        <?php foreach ($themes as $theme) : ?>
                            <th scope="col" class="text-center" style="width: 9rem"><?= e(ThemeSupport::label($theme)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $groupKey => $docs) : ?>
                        <?php if ($groupKey !== '' && $category !== null && $category->groupTitle($groupKey) !== '') : ?>
                            <tr class="table-light">
                                <th scope="rowgroup" colspan="<?= e((string) (2 + count($themes))) ?>" class="small text-uppercase text-body-secondary fw-semibold"><?= e($category->groupTitle($groupKey)) ?></th>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($docs as $item) : $availability = $catalogAvailability[$item->getSlug()] ?? []; ?>
                            <tr>
                                <th scope="row" class="fw-semibold">
                                    <a href="<?= e($urls->component($item->getSlug())) ?>" class="text-decoration-none"><?= e($item->getTitle()) ?></a>
                                    <?php if ($item->getDeprecated() !== null) : ?><span class="badge text-bg-warning ms-1">deprecato</span><?php endif; ?>
                                    <div class="small text-body-tertiary fw-normal"><code><?= e($item->shortName()) ?></code></div>
                                </th>
                                <td class="text-body-secondary"><?= Pages::text($item->getDescription()) ?></td>
                                <?php foreach ($themes as $theme) : ?>
                                    <td class="text-center">
                                        <?= isset($availability[$theme]) ? Pages::availabilityCell($availability[$theme], $item->getNote($theme)) : '' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>

<?php if ($grouped === []) : ?>
    <div class="alert alert-warning">
        Nessuna scheda trovata. Le schede stanno in <code>docs/components/&lt;categoria&gt;/*.php</code> del pacchetto.
    </div>
<?php endif; ?>
