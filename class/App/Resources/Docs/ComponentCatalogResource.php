<?php

namespace Wonder\App\Resources\Docs;

use Wonder\App\LegacyGlobals;
use Wonder\App\Path;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Docs\Urls;

/**
 * La voce "Componenti" della sezione Dev del backend: il catalogo dei
 * componenti del framework, reso con il CSS del sito. Le pagine (indice e
 * scheda) passano dall'entry condivisa `resource/page.php`; l'anteprima
 * dentro l'iframe ha il suo handler in `http/backend/docs/preview.php`. Le route
 * sono quelle di route.backend.php: la base disabilita già le pagine CRUD.
 */
class ComponentCatalogResource extends NavigationOnlyResource
{
    public static function path(): string { return 'app/docs/components'; }
    public static function icon(): string { return 'bi-puzzle'; }
    public static function titleLabel(): string { return 'Componenti'; }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->title('Componenti')
            ->order(80)
            ->authority(['admin']);
    }

    public static function pageView(): string
    {
        return (string) LegacyGlobals::get('ROOT_APP', '').'/view/pages/backend/docs/components.php';
    }

    /** Gli URL del catalogo sotto il backend del sito. */
    public static function urls(): Urls
    {
        return new Urls(rtrim((string) (new Path())->backend, '/').'/'.static::path());
    }
}
