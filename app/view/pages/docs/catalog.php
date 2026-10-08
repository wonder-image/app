<?php

/**
 * La cornice del catalogo: barra laterale più la pagina chiesta (`index` o
 * `component`). La include il backend dentro il suo layout e il server
 * autonomo dentro `layout/docs/base.php`. Variabili da `Wonder\Docs\CatalogPage`.
 *
 * @var string $view
 */

use Wonder\Docs\Pages;

$view = in_array($view ?? '', ['index', 'component'], true) ? $view : 'index';

?>
<style data-wi-docs-style>
    .wi-docs-sidebar { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); overflow: auto; font-size: .9375rem; }
    .wi-docs-content { min-width: 0; }
    .wi-docs-content h2 { scroll-margin-top: 1rem; }
    .wi-docs-example { scroll-margin-top: 1rem; }
    .wi-docs-table td, .wi-docs-table th { vertical-align: middle; }
    .wi-docs-table code { font-size: .8125rem; }
    .wi-docs-api code { white-space: pre-wrap; }
    .wi-docs-switch .btn.active { background-color: var(--bs-secondary); color: var(--bs-white); border-color: var(--bs-secondary); }
</style>

<div class="row g-4">
    <div class="col-12 col-lg-3 col-xxl-2">
        <?php include __DIR__.'/partials/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-9 col-xxl-10 wi-docs-content">
        <?php include __DIR__.'/'.$view.'.php'; ?>
    </div>
</div>

<?= Pages::themeSwitchScript() ?>
<?= Pages::sidebarScript() ?>
