<?php

// Entry http condivisa per le pagine backend non-CRUD gestite da un Resource
// (tipicamente NavigationOnlyResource). La logica di dominio e l'azione di
// submit vivono nel Resource; la presentazione nella sua `pageView()`. Qui solo
// la colla: risolvi il Resource dallo slug, esegui l'eventuale submit, rendi la
// view. Come le route CRUD condividono `index.php`, le pagine custom condividono
// questo dispatcher: nessun handler dedicato per pagina.

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$resourceSlug = trim((string) ($routeMeta['resource'] ?? ''));

if ($resourceSlug === '' || !\Wonder\App\ResourceRegistry::has($resourceSlug)) {
    throw new RuntimeException('Resource backend non trovata per la pagina: '.$resourceSlug);
}

$resourceClass = \Wonder\App\ResourceRegistry::resolve($resourceSlug);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_callable([$resourceClass, 'handleSubmit'])) {
    $resourceClass::handleSubmit();
}

if (!is_callable([$resourceClass, 'pageView'])) {
    throw new RuntimeException($resourceClass.' non espone pageView() per una pagina backend.');
}

\Wonder\View\View::make($resourceClass::pageView())->render();
