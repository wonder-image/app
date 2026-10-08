<?php

/**
 * La scheda di un componente: descrizione, disponibilità per tema, esempi con
 * anteprima e codice, riferimento API. Variabili da
 * `Wonder\Docs\CatalogPage::component()`.
 *
 * La pagina è fatta con gli stessi Element che documenta (tema Bootstrap
 * esplicito); resta HTML solo la tabella dei metodi, che non ha un Element.
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
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;
use Wonder\Elements\Components\RichText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Components\Steps;
use Wonder\Elements\Components\Text;

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

$methodsTable = static function (array $rows): string {
    return '<div class="table-responsive"><table class="table table-sm align-middle wi-docs-table mb-0"><tbody>'.implode('', $rows).'</tbody></table></div>';
};

// Il percorso: Catalogo › Categoria › Gruppo › Componente, come passi fatti e passo corrente.
$breadcrumb = Steps::make('Percorso')->step('Catalogo', $urls->index(), 'done');

if ($category !== null) {
    $breadcrumb->step($category->title, $urls->index().'#'.$category->key, 'done');
}

if ($groupTitle !== '') {
    $breadcrumb->step($groupTitle, $urls->index().'#'.$doc->getCategory(), 'done');
}

$breadcrumb->step($doc->getTitle(), null, 'current');

// Le note per tema, solo dove c'è qualcosa da dire.
$themeCards = [];

foreach ($availability as $theme => $support) {
    $note = $doc->getNote($theme);

    if ($note === '' && $support->available && !$support->inherited) {
        continue;
    }

    $parts = [SectionTitle::make($support->label())->level(6)];

    if (!$support->available || $support->inherited) {
        $parts[] = Text::make(Pages::text($support->reason))->html()->small()->muted()->tag('div');
    }

    if ($note !== '') {
        $parts[] = Text::make(Pages::text($note))->html()->small()->tag('div');
    }

    $themeCards[] = (new Card())->components($parts);
}

// I rimandi alla guida, come link con l'icona del libro.
$guide = null;

if ($doc->getDocs() !== []) {
    $guide = Text::make('')->tag('p')->small();

    foreach ($doc->getDocs() as $position => $link) {
        if ($position > 0) {
            $guide->append(' · ');
        }

        $guide->append(Link::to($urls->guide($link['href']), $link['label'])->blank()->icon($position === 0 ? 'bi bi-book' : ''));
    }
}

?>
<div class="mb-2 small"><?= $breadcrumb->render('bootstrap') ?></div>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?= SectionTitle::make($doc->getTitle())->level(3)->render('bootstrap') ?>
            <span class="fs-6"><?= Pages::badges($doc, $availability) ?></span>
        </div>
        <?= Text::make('<code>'.e($doc->getClass()).'</code>')->html()->small()->muted()->tag('div')->render('bootstrap') ?>
    </div>
    <?= Pages::themeSwitch($themes) ?>
</div>

<?php if ($doc->getDeprecated() !== null) : ?>
    <div class="mb-3"><?= Alert::make($doc->getDeprecated(), 'warning')->title('Deprecato')->dismissible(false)->render('bootstrap') ?></div>
<?php endif; ?>

<?php if ($doc->getDescription() !== '') : ?>
    <?= Text::make(Pages::text($doc->getDescription(), true))->html()->tag('div')->addClass('fs-6 mb-3')->render('bootstrap') ?>
<?php endif; ?>

<?php if ($themeCards !== []) : ?>
    <div class="mb-4"><?= (new Container())->columns(2)->gap(2)->components($themeCards)->render('bootstrap') ?></div>
<?php endif; ?>

<?php if ($guide !== null) : ?>
    <div class="mb-4"><?= $guide->render('bootstrap') ?></div>
<?php endif; ?>

<?php if ($examples === []) : ?>
    <?= Alert::make('Questa scheda non ha ancora esempi.', 'info')->dismissible(false)->render('bootstrap') ?>
<?php endif; ?>

<?php foreach ($examples as $entry) : $example = $entry['example']; ?>
    <section class="wi-docs-example mb-5" id="esempio-<?= e((string) ($entry['index'] + 1)) ?>">
        <?= SectionTitle::make($example->getTitle())->level(5)->render('bootstrap') ?>
        <?php if ($example->getDescription() !== '') : ?>
            <?= Text::make(Pages::text($example->getDescription(), true))->html()->muted()->tag('div')->addClass('mb-2')->render('bootstrap') ?>
        <?php endif; ?>
        <?= Pages::preview($doc, $entry['index'], $example, $availability, $urls)->render('bootstrap') ?>
        <div class="mt-2"><?= Pages::code($entry['code'])->render('bootstrap') ?></div>
    </section>
<?php endforeach; ?>

<section class="wi-docs-api mb-5" id="api">
    <?= SectionTitle::make('API')->level(5)->render('bootstrap') ?>
    <?= Text::make('Metodi pubblici letti dalla classe; getter e <code>render()</code> esclusi. Tutti i setter ritornano l\'istanza, quindi si concatenano.')->html()->small()->muted()->tag('p')->render('bootstrap') ?>
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
        <?php
            $inherited = [];

            foreach ($api['inherited'] as $origin => $methods) {
                $inherited[] = SectionTitle::make('Da '.$origin)->level(6);
                $inherited[] = RichText::make($methodsTable(array_map($methodRow, $methods)))->tag('div');
            }
        ?>
        <?= Accordion::make('Metodi ereditati da classi madri e concern')->flush()->components($inherited)->render('bootstrap') ?>
    <?php endif; ?>
</section>

<?php if ($related !== []) : ?>
    <section class="mb-4">
        <?= SectionTitle::make('Vedi anche')->level(6)->render('bootstrap') ?>
        <?= ButtonGroup::make(array_map(
            static fn (\Wonder\Docs\ComponentDoc $item): Button => Button::to($urls->component($item->getSlug()), $item->getTitle())->variant('secondary')->outline()->size('sm'),
            $related
        ))->label('Componenti correlati')->render('bootstrap') ?>
    </section>
<?php endif; ?>

<div class="d-flex justify-content-between gap-3 border-top pt-3 small">
    <div>
        <?php if ($neighbors['previous'] !== null) : ?>
            <?= Link::to($urls->component($neighbors['previous']->getSlug()), $neighbors['previous']->getTitle())->icon('bi bi-arrow-left-short')->render('bootstrap') ?>
        <?php endif; ?>
    </div>
    <div class="text-end">
        <?php if ($neighbors['next'] !== null) : ?>
            <?= Link::to($urls->component($neighbors['next']->getSlug()), $neighbors['next']->getTitle())->icon('bi bi-arrow-right-short', 'end')->render('bootstrap') ?>
        <?php endif; ?>
    </div>
</div>
