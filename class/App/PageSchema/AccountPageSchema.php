<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormInput;

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
            'profile_picture' => FormInput::key('profile_picture')->inputFileDragDrop('image', 'profile'),
            'name' => FormInput::key('name')->text()->required(),
            'surname' => FormInput::key('surname')->text()->required(),
            'username' => FormInput::key('username')->text()->required(),
            'phone' => FormInput::key('phone')->phone(),
            'color' => FormInput::key('color')->select($colorOptions),
            'email' => FormInput::key('email')->email()->required(),
            'password' => FormInput::key('password')->password()->required(),
        ]);
    }

    public static function passwordFormSchema(): array
    {
        return static::applyLabelSchema([
            'old-password' => FormInput::key('old-password')->password()->required(),
            'new-password' => FormInput::key('new-password')->password()->required(),
        ]);
    }

    public static function loginFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormInput::key('username')->text()->required(),
            'password' => FormInput::key('password')->password()->required(),
        ]);
    }

    public static function recoveryFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormInput::key('username')->text()->required(),
        ]);
    }

    public static function restoreFormSchema(): array
    {
        return static::applyLabelSchema([
            'username' => FormInput::key('username')->text()->disabled(),
            'password' => FormInput::key('password')->password()->required(),
        ]);
    }

    public static function setPasswordFormSchema(): array
    {
        return static::applyLabelSchema([
            'password' => FormInput::key('password')->password()->required(),
        ]);
    }
}
