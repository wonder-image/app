<?php

namespace Wonder\App\Resources\Config;

use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class SqlErrorResource extends Resource
{
    public static string $model = \Wonder\App\Models\Config\SqlError::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'errore',
            'plural_label' => 'errori',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'gli',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'function' => 'Funzione',
            'table' => 'Tabella',
            'query' => 'Query',
            'error_n' => 'Errore N°',
            'error' => 'Errore',
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('table')->text()->link('view'),
            TableColumn::key('error_n')->text()->size('little'),
            TableColumn::key('error')->text(),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->hideButtonAdd()
            ->filters()
            ->searchFields(['table', 'error_n', 'error', 'query'])
            ->filterCustom('Tabella', 'table', static::tableFilterOptions(), 'checkbox', true)
            ->filterCustom('Errore N°', 'error_n', static::errorNumberFilterOptions(), 'checkbox', true);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['list', 'view'])
            ->view('show', static::customShowViewPath())
            ->title('view', 'Dettaglio errore SQL');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'view'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('log')
            ->title('Errori SQL')
            ->order(40)
            ->authority(['admin', 'administrator']);
    }

    private static function customShowViewPath(): ?string
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp !== ''
            ? $rootApp.'/view/pages/backend/config/sql-error/show.php'
            : null;
    }

    private static function tableFilterOptions(): array
    {
        try {
            $rows = static::modelClass()::query()->Select(static::modelTable(), ['deleted' => 'false'])->row ?? [];
        } catch (Throwable) {
            return [];
        }

        $options = [];

        foreach ((array) $rows as $row) {
            $value = trim((string) ($row['table'] ?? ''));
            if ($value !== '') {
                $options[$value] = $value;
            }
        }

        ksort($options);

        return $options;
    }

    private static function errorNumberFilterOptions(): array
    {
        try {
            $rows = static::modelClass()::query()->Select(static::modelTable(), ['deleted' => 'false'])->row ?? [];
        } catch (Throwable) {
            return [];
        }

        $options = [];

        foreach ((array) $rows as $row) {
            $value = trim((string) ($row['error_n'] ?? ''));
            if ($value !== '') {
                $options[$value] = $value;
            }
        }

        ksort($options, SORT_NATURAL);

        return $options;
    }
}
