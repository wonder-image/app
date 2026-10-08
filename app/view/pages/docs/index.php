<?php

/**
 * L'indice del catalogo: panoramica dei componenti per categoria e gruppo,
 * con la disponibilità in ogni tema. Variabili da `Wonder\Docs\CatalogPage::index()`.
 *
 * La pagina è fatta con gli stessi Element che documenta (tema Bootstrap
 * esplicito); resta HTML solo la tabella, che non ha un Element.
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
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;
use Wonder\Elements\Components\MetricCard;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Components\Text;

$tiles = [MetricCard::make('Componenti', $counts['total'])];

foreach ($themes as $theme) {
    $tiles[] = MetricCard::make('Tema '.ThemeSupport::label($theme), $counts[$theme] ?? 0);
}

$tiles[] = MetricCard::make('In tutti i temi', $counts['both']);

?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
        <?= SectionTitle::make('Catalogo dei componenti')->level(3)->render('bootstrap') ?>
        <?= Text::make('Ogni Element di <code>wonder-image/app</code> con il codice da copiare e l\'anteprima resa dal tema scelto. Le anteprime Bootstrap si vedono anche in modalità scura.')->html()->muted()->tag('p')->render('bootstrap') ?>
    </div>
    <?= Pages::themeSwitch($themes) ?>
</div>

<div class="mb-4">
    <?= (new Container())->columns(4)->gap(3)->components($tiles)->render('bootstrap') ?>
</div>

<div class="mb-4">
    <?= Alert::make('La disponibilità non è scritta a mano: per ogni componente il catalogo chiede al Themes\Resolver se esiste il renderer del tema, lo stesso che usa render($theme). «Ereditato» indica un renderer preso da una classe madre.', 'info')
        ->title('Disponibilità per tema')
        ->dismissible(false)
        ->render('bootstrap') ?>
</div>

<?php foreach ($grouped as $categoryKey => $groups) : $category = $categories[$categoryKey] ?? null; ?>
    <section class="mb-5" id="<?= e($categoryKey) ?>">
        <?= SectionTitle::make($category?->title ?? $categoryKey)->level(4)->render('bootstrap') ?>
        <?php if ($category !== null && $category->description !== '') : ?>
            <?= Text::make(Pages::text($category->description))->html()->muted()->tag('p')->render('bootstrap') ?>
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
                                    <?= Link::to($urls->component($item->getSlug()), $item->getTitle())->render('bootstrap') ?>
                                    <?php if ($item->getDeprecated() !== null) : ?>
                                        <?= Badge::make('deprecato')->variant('warning')->title($item->getDeprecated())->render('bootstrap') ?>
                                    <?php endif; ?>
                                    <?= Text::make('<code>'.e($item->shortName()).'</code>')->html()->small()->muted()->tag('div')->render('bootstrap') ?>
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
    <?= Alert::make('Nessuna scheda trovata. Le schede stanno in docs/components/<categoria>/*.php del pacchetto.', 'warning')->dismissible(false)->render('bootstrap') ?>
<?php endif; ?>
