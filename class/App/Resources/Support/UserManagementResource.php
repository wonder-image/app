<?php

namespace Wonder\App\Resources\Support;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Http\Route;

abstract class UserManagementResource extends Resource
{
    abstract public static function managedArea(): string;

    abstract protected static function permissionsFunction(): string;

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('profile_picture')->inputFileDragDrop('image', 'profile')->label(''),
            FormInput::key('name')->text()->required(),
            FormInput::key('surname')->text()->required(),
            FormInput::key('color')->select(static::colorOptions()),
            FormInput::key('username')->text()->required(),
            FormInput::key('phone')->phone(),
            FormInput::key('email')->email()->required(),
            FormInput::key('authority')->select(static::availableAuthorities())->required(),
            FormInput::key('active')->select([
                'true' => 'Abilitato',
                'false' => 'Disabilitato',
            ])->required(),
        ];
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['view', 'delete']);
    }

    public static function customBackendPages(): array
    {
        return ['create', 'store', 'edit', 'update'];
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'edit', 'update'], ['admin']);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('username')->text()->link('edit'),
            TableColumn::key('name')->text()->columns(['name', 'surname']),
            TableColumn::key('email')->text()->link('mailto'),
            TableColumn::key('authority')->badge()->function(static::permissionsFunction(), 'authority', 'automaticResize'),
            TableColumn::key('active')->badge()->function('active', 'id', 'automaticResize')->size('little'),
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters()
            ->searchFields(['username', 'name', 'surname', 'email'])
            ->filterRadio('Autorizzazione', 'authority', ['' => 'Tutte'] + static::availableAuthorities())
            ->filterRadio('Stato', 'active', [
                '' => 'Tutti',
                'true' => 'Abilitato',
                'false' => 'Disabilitato',
            ]);
    }

    public static function querySchema(): array
    {
        $conditions = [
            "`deleted` = 'false'",
            "`area` LIKE '%".static::managedArea()."%'",
        ];

        if (static::managedArea() === 'backend' && !in_array('admin', static::currentUserAuthorities(), true)) {
            $conditions[] = "`authority` NOT LIKE '%\\\"admin\\\"%'";
        }

        return [
            'condition' => implode(' AND ', $conditions),
            'limit' => null,
            'order' => [
                'column' => 'creation',
                'direction' => 'DESC',
            ],
        ];
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/create/', $rootApp.'/http/backend/user/manage.php', [
            'resource' => $slug,
            'resource_action' => 'create',
        ])->name('create')->permit(['admin']);

        Route::post('/create/', $rootApp.'/http/backend/user/manage.php', [
            'resource' => $slug,
            'resource_action' => 'store',
        ])->name('store')->permit(['admin']);

        Route::get('/{id}/edit/', $rootApp.'/http/backend/user/manage.php', [
            'resource' => $slug,
            'resource_action' => 'edit',
        ])->name('edit')
            ->permit(['admin'])
            ->where('id', '[0-9]+');

        Route::post('/{id}/edit/', $rootApp.'/http/backend/user/manage.php', [
            'resource' => $slug,
            'resource_action' => 'update',
        ])->name('update')
            ->permit(['admin'])
            ->where('id', '[0-9]+');
    }

    public static function availableAuthorities(): array
    {
        $function = static::permissionsFunction();
        $available = [];

        foreach ((array) call_user_func($function) as $key => $label) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $permission = permissions($key);
            $creators = is_object($permission) ? (array) ($permission->creator ?? []) : [];

            if (
                $creators === []
                || in_array('admin', static::currentUserAuthorities(), true)
                || count(array_intersect($creators, static::currentUserAuthorities())) >= 1
            ) {
                $available[$key] = $label;
            }
        }

        return $available;
    }

    public static function existingUserOptions(): array
    {
        if (!in_array('admin', static::currentUserAuthorities(), true)) {
            return [];
        }

        $options = ['' => 'No'];
        $area = static::managedArea();
        $query = "`area` NOT LIKE '%{$area}%' and `deleted` = 'false'";

        foreach ((array) sqlSelect('user', $query)->row as $row) {
            $id = (int) ($row['id'] ?? 0);
            $username = trim((string) ($row['username'] ?? ''));

            if ($id > 0 && $username !== '') {
                $options[$id] = $username;
            }
        }

        return $options;
    }

    public static function selectedAuthority(array $values): ?string
    {
        $authority = $values['authority'] ?? null;
        $area = static::managedArea();

        if (is_string($authority) && $authority !== '') {
            $decoded = json_decode($authority, true);
            $authority = is_array($decoded) ? $decoded : [$authority];
        }

        foreach ((array) $authority as $value) {
            $permission = permissions((string) $value);

            if (is_object($permission) && (($permission->area ?? null) === $area)) {
                return (string) $value;
            }
        }

        return null;
    }

    public static function canCreateFromExisting(): bool
    {
        return in_array('admin', static::currentUserAuthorities(), true);
    }

    public static function sendsWelcomeMail(): bool
    {
        return static::managedArea() === 'backend';
    }

    protected static function currentUserAuthorities(): array
    {
        $user = LegacyGlobals::get('USER');

        if (!is_object($user)) {
            return [];
        }

        return is_array($user->authority ?? null) ? $user->authority : [];
    }

    private static function colorOptions(): array
    {
        $default = LegacyGlobals::get('DEFAULT');
        $options = [];

        foreach ((array) ($default->colorUser ?? []) as $key => $color) {
            if (($color['active'] ?? false) === true) {
                $options[$key] = (string) ($color['name'] ?? $key);
            }
        }

        return $options;
    }
}
