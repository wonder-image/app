<?php

namespace Wonder\App\Resources\User;

use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\UserManagementResource;

final class BackendUserResource extends UserManagementResource
{
    public static string $model = \Wonder\App\Models\User\User::class;

    public static function managedArea(): string
    {
        return 'backend';
    }

    protected static function permissionsFunction(): string
    {
        return 'permissionsBackend';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'utente',
            'plural_label' => 'utenti',
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
            'username' => 'Username',
            'name' => 'Nome',
            'email' => 'Email',
            'authority' => 'Autorizzazione',
            'active' => 'Stato',
            'actions' => 'Azioni',
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Set Up', 'set-up', 'bi-gear')
            ->title('Utenti')
            ->order(40)
            ->authority(['admin']);
    }
}
