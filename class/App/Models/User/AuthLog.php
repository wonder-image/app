<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class AuthLog extends Model
{
    public static string $table = 'auth_log';
    public static string $folder = 'app/log/auth-users';
    public static string $icon = 'bi bi-shield-check';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('event')->length(50),
            Column::key('area')->length(20),
            Column::key('success')->bool(),
            Column::key('ip')->length(45),
            Column::key('user_agent')->length(255),
            Column::key('meta')->json(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_user_event' => [
                'index' => ['user_id', 'event'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('event')->text()->required(),
            Field::key('area')->text()->required(),
            Field::key('success')->text()->required(),
            Field::key('ip')->text(),
            Field::key('user_agent')->text(),
            Field::key('meta')->text()->json()->sanitize(false),
        ];
    }
}
