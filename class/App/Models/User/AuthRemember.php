<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class AuthRemember extends Model
{
    public static string $table = 'auth_remember';
    public static string $folder = 'app/log/auth-users';
    public static string $icon = 'bi bi-shield-lock';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('selector')->length(64),
            Column::key('token_hash')->length(128),
            Column::key('area')->length(20),
            Column::key('expires_at')->datetime(),
            Column::key('last_used')->datetime(),
            Column::key('ip')->length(45),
            Column::key('user_agent')->length(255),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_selector' => [
                'index' => 'selector',
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('selector')->text()->required(),
            Field::key('token_hash')->text()->required(),
            Field::key('area')->text()->required(),
            Field::key('expires_at')->text()->required(),
            Field::key('last_used')->text()->required(),
            Field::key('ip')->text(),
            Field::key('user_agent')->text(),
        ];
    }
}
