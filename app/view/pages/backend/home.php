<?php \Wonder\View\View::layout('backend.main'); ?>
<?php

use Wonder\Backend\Support\{BackendNavigation, HomeNavigation, HomeStatus, HomeWidgets};
use Wonder\Elements\Components\{Button, LinkCard, LinkListCard};

// Solo Element e classi Bootstrap: i dati arrivano già filtrati per autorità
// da HomeNavigation (sezioni) e HomeStatus (voci di stato, una per chiave).
$authorities = (array) ($USER->authority ?? []);
$backendUrl = (string) ($PATH->backend ?? '/backend');
$sections = HomeNavigation::sections(BackendNavigation::all(), $authorities, $backendUrl);
$shortcuts = HomeNavigation::shortcuts($sections);
$areas = array_values(array_filter($sections, static fn (array $section): bool => $section['href'] === null));
$status = HomeStatus::items($authorities, $backendUrl);
$widgets = HomeWidgets::renderAll($authorities);

$hour = (int) date('G');
$name = trim((string) ($USER->name ?? ''));
$greeting = ($hour < 13 ? 'Buongiorno' : ($hour < 18 ? 'Buon pomeriggio' : 'Buonasera')).($name !== '' ? ', '.$name : '');
$today = class_exists(IntlDateFormatter::class)
    ? (string) (new IntlDateFormatter('it_IT', IntlDateFormatter::FULL, IntlDateFormatter::NONE))->format(time())
    : date('d/m/Y');
$site = trim((string) ($PATH->site ?? ''));
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <p class="small text-body-secondary text-uppercase fw-semibold mb-1"><?=e($today)?></p>
        <h1 class="h3 mb-1"><?=e($greeting)?></h1>
        <p class="text-body-secondary mb-0">Da dove vuoi partire?</p>
    </div>
    <?php if ($site !== '') { ?>
        <?=Button::make('Vai al sito', $site)->variant('secondary')->outline()->blank()->render('bootstrap')?>
    <?php } ?>
</div>

<?php if ($status !== []) { ?>
    <div class="row g-3 mb-4">
        <?php foreach ($status as $card) { ?>
            <div class="col-12 col-md-6 col-xl-3"><?=$card->render('bootstrap')?></div>
        <?php } ?>
    </div>
<?php } ?>

<?php if ($widgets !== '') { ?>
    <?php // Riquadri dichiarati dai moduli abilitati (backend.home_widgets). ?>
    <div class="row g-3 mb-4"><?=$widgets?></div>
<?php } ?>

<?php if ($shortcuts !== []) { ?>
    <h2 class="h6 text-body-secondary text-uppercase fw-semibold mb-3">Accesso rapido</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($shortcuts as $shortcut) { ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                <?=LinkCard::make($shortcut['title'], $shortcut['href'])->icon($shortcut['icon'])->render('bootstrap')?>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<?php if ($areas !== []) { ?>
    <h2 class="h6 text-body-secondary text-uppercase fw-semibold mb-3">Sezioni</h2>
    <div class="row g-3">
        <?php foreach ($areas as $section) {
            $card = LinkListCard::make($section['title'])->icon($section['icon'])->links($section['links']);
        ?>
            <?php // Una sezione con gruppi prende tutta la riga: un gruppo per colonna. ?>
            <div class="<?=$card->isGrouped() ? 'col-12' : 'col-12 col-md-6 col-xxl-4'?>"><?=$card->render('bootstrap')?></div>
        <?php } ?>
    </div>
<?php } ?>
<?php \Wonder\View\View::end(); ?>
