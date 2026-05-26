<?php

namespace Wonder\App\Resources\Support;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableLayoutSchema;

/**
 * Base per Resource "navigation-only": esistono solo per dichiarare una
 * voce di menu nel backend, NON sono entità con un model DB dietro.
 *
 * Sono utili per pagine utility/admin (es. "Upload di massa", "Dati
 * aziendali", "Download SQL") che hanno una `PageSchema` propria ma
 * non un model CRUD. Prima del runtime registry queste voci erano
 * hardcoded in `BackendNavigationSections`; ora sono Resource leggere
 * che usano `inSection()` come tutte le altre.
 *
 * Le sottoclassi DEVONO override:
 *  - `path()`         — URL/folder della pagina
 *  - `icon()`         — non viene letta dal model, va fornita esplicita
 *  - `titleLabel()`   — titolo mostrato (default Resource::titleLabel
 *                       leggerebbe da model schema, qui non esiste)
 *  - `navigationSchema()` — configurazione del posizionamento
 *
 * `modelClass()` e operazioni CRUD lanciano eccezioni se invocate:
 * queste Resource non hanno un model, e il routing CRUD non si applica.
 * Solo la navigation backend e la PageSchema le toccano.
 */
abstract class NavigationOnlyResource extends Resource
{
    public static function modelClass(): string
    {
        throw new RuntimeException(
            static::class.' è una NavigationOnlyResource: non ha model. '
            .'Le operazioni CRUD/query non sono supportate; la pagina è '
            .'gestita da una PageSchema dedicata.'
        );
    }

    public static function modelTable(): string
    {
        return '';
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->hideTitle()
            ->hideButtonAdd()
            ->filters(false, false);
    }

    public static function pageSchema(): PageSchema
    {
        # Disabilita tutte le azioni CRUD del page schema. La pagina
        # vera è gestita altrove (di solito da una PageSchema
        # specifica con form/processor custom).
        return PageSchema::for(static::class)
            ->disable(['create', 'store', 'edit', 'update', 'view', 'delete']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->only([]);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class);
    }

    public static function tableSchema(): array
    {
        return [];
    }

    public static function formSchema(): array
    {
        return [];
    }

    /**
     * Override del Resource::prepareSchema() — l'originale chiama
     * `modelClass()::prepareFormatFromField()` per ogni campo, cosa
     * che fallisce qui (no model). Per le NavigationOnlyResource non
     * c'è uno schema "table" da preparare: la pagina la gestisce una
     * PageSchema dedicata che pubblica il proprio schema sotto un
     * altro nome (vedi `CustomPageSchema`).
     */
    public static function prepareSchema(): array
    {
        return [];
    }

    # `path()`, `icon()`, `navigationSchema()` non sono dichiarati
    # abstract perché Resource fornisce già default (concreti). Le
    # sottoclassi devono comunque overrid-arli, altrimenti `icon()` /
    # `path()` cadrebbero sul fallback al model che non esiste e
    # lancerebbe `modelClass()` runtime error.
}
