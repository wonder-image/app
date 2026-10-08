<?php

/**
 * La barra laterale del catalogo: le schede per categoria e gruppo, con il
 * filtro di testo. Variabili da `Wonder\Docs\CatalogPage`.
 *
 * @var \Wonder\Docs\Urls $urls
 * @var array<string, \Wonder\Docs\Category> $categories
 * @var array<string, array<string, \Wonder\Docs\ComponentDoc[]>> $grouped
 * @var ?\Wonder\Docs\ComponentDoc $doc
 */

$currentSlug = $doc?->getSlug() ?? '';

?>
<nav class="wi-docs-sidebar" aria-label="Componenti" data-wi-docs-sidebar>
    <div class="mb-3">
        <input type="search" class="form-control form-control-sm" placeholder="Cerca un componente" aria-label="Cerca un componente" data-wi-docs-filter autocomplete="off">
    </div>

    <?php foreach ($grouped as $categoryKey => $groups) : $category = $categories[$categoryKey] ?? null; ?>
        <div class="mb-3" data-wi-docs-section>
            <a href="<?= e($urls->index()) ?>#<?= e($categoryKey) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-reset small fw-semibold text-uppercase mb-1">
                <?php if ($category !== null && $category->icon !== '') : ?><i class="bi <?= e($category->icon) ?>" aria-hidden="true"></i><?php endif; ?>
                <?= e($category?->title ?? $categoryKey) ?>
            </a>
            <?php foreach ($groups as $groupKey => $docs) : ?>
                <?php if ($groupKey !== '' && $category !== null && $category->groupTitle($groupKey) !== '') : ?>
                    <div class="small text-body-secondary mt-2 mb-1 ps-1" data-wi-docs-item="<?= e($category->groupTitle($groupKey)) ?>"><?= e($category->groupTitle($groupKey)) ?></div>
                <?php endif; ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($docs as $item) : $active = $item->getSlug() === $currentSlug; ?>
                        <li data-wi-docs-item="<?= e($item->getTitle().' '.$item->shortName().' '.implode(' ', $item->getTags())) ?>">
                            <a href="<?= e($urls->component($item->getSlug())) ?>" class="d-block rounded px-2 py-1 text-decoration-none <?= $active ? 'bg-primary-subtle text-primary-emphasis fw-semibold' : 'text-reset' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <?= e($item->getTitle()) ?>
                                <?php if ($item->getDeprecated() !== null) : ?><span class="badge text-bg-warning ms-1" title="<?= e($item->getDeprecated()) ?>">deprecato</span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</nav>
