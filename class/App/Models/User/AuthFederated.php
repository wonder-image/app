<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class AuthFederated extends Model
{
    public static string $table = 'auth_federated';
    public static string $folder = 'app/log/auth-users';
    public static string $icon = 'bi bi-person-badge';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('provider')->length(20),
            Column::key('provider_user_id')->length(191),
            Column::key('provider_email')->length(255)->null(),
            Column::key('provider_email_verified')->bool()->default('false'),
            Column::key('last_login_at')->datetime()->null(),
            Column::key('meta')->json(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'uq_provider_subject' => [
                'unique' => ['provider', 'provider_user_id'],
            ],
            'uq_user_provider' => [
                'unique' => ['user_id', 'provider'],
            ],
            'ind_user_provider' => [
                'index' => ['user_id', 'provider'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('provider')->text()->required(),
            Field::key('provider_user_id')->text()->required(),
            Field::key('provider_email')->text(),
            Field::key('provider_email_verified')->text(),
            Field::key('last_login_at')->text(),
            Field::key('meta')->text()->json()->sanitize(false),
        ];
    }
}
