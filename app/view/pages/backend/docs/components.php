<?php

/**
 * Il catalogo dei componenti nel backend: indice (senza parametro) o scheda
 * (`{component}` nella route). I dati vengono da `CatalogPage`, la cornice è
 * la stessa del server autonomo (`pages/docs/catalog.php`).
 */

use Wonder\App\LegacyGlobals;
use Wonder\App\Resources\Docs\ComponentCatalogResource;
use Wonder\Docs\CatalogPage;
use Wonder\View\View;

$parameters = LegacyGlobals::get('ROUTE_PARAMETERS', []);
$slug = is_array($parameters) ? strtolower(trim((string) ($parameters['component'] ?? ''))) : '';
$urls = ComponentCatalogResource::urls();
$data = $slug === '' ? CatalogPage::index($urls) : CatalogPage::component($slug, $urls);

if ($data === null) {
    http_response_code(404);
    View::layout('backend.main', ['TITLE' => 'Componente non trovato']);
    echo '<div class="alert alert-warning">Nessun componente con slug <code>'.e($slug).'</code>. <a href="'.e($urls->index()).'" class="alert-link">Torna al catalogo</a>.</div>';
    View::end();

    return;
}

$TITLE = $data['doc'] !== null ? $data['doc']->getTitle().' · Componenti' : 'Catalogo dei componenti';

View::layout('backend.main', ['TITLE' => $TITLE]);

View::make((string) LegacyGlobals::get('ROOT_APP', '').'/view/pages/docs/catalog.php', $data)->render();

View::end();
