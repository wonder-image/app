<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ApiUser extends Model
{
    public static string $table = 'api_users';
    public static string $folder = 'app/config/api-users';
    public static string $icon = 'bi bi-key';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('allowed_domains')->json(),
            Column::key('allowed_ips')->json(),
            Column::key('token')->length(512)->unique(),
            Column::key('active')->default('true'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('allowed_domains')->text()->json()->sanitize(false),
            Field::key('allowed_ips')->text()->json()->sanitize(false),
            Field::key('token')->text()->unique()->sanitize(false),
            Field::key('active')->text(),
        ];
    }
}
