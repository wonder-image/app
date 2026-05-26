<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormField;

final class AccountPageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'profile_picture' => '',
            'name' => 'Nome',
            'surname' => 'Cognome',
            'username' => 'Username',
            'phone' => 'Cellulare',
            'color' => 'Colore',
            'email' => 'Email',
            'password' => 'Password',
            'old-password' => 'Vecchia password',
            'new-password' => 'Nuova password',
        ];
    }

    public static function profileFormSchema(array $colorOptions): array
    {
        return static::applyLabelSchema([
            'profile_picture' => FormField::key('profile_picture')->inputFileDragDrop('image', 'profile'),
            'name' => FormField::key('name')->text()->required(),
            'surname' => FormField::key('surname')->text()->required(),
            'username' => FormField::key('username')->text()->required(),
            'phone' => FormField::key('phone')->phone(),
            'color' => FormField::key('color')->select($colorOptions),
            'email' => FormField::key('email')->email()->required(),
            'password' => FormField::key('password')->password()->required(),
        ]);
    }

    public static function passwordFormSchema(): array
    {
        return static::applyLabelSchema([
            'old-password' => FormField::key('old-password')->password()->required(),
            'new-password' => FormField::key('new-password')->password()->required(),
        ]);
    }

    public static function loginFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormField::key('username')->text()->label('Username o email')->required(),
            'password' => FormField::key('password')->password()->required(),
        ]);
    }

    public static function recoveryFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormField::key('username')->text()->required(),
        ]);
    }

    public static function restoreFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormField::key('username')->text()->disabled(),
            'password' => FormField::key('password')->password()->required(),
        ]);
    }

    public static function setPasswordFormSchema(): array
    {
        return static::applyLabelSchema([
            'password' => FormField::key('password')->password()->required(),
        ]);
    }
}
