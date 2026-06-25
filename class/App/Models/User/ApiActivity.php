<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ApiActivity extends Model
{
    public static string $table = 'api_activity';
    public static string $folder = 'app/log/api-activity';
    public static string $icon = 'bi bi-activity';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('token_id')->int()->foreign('api_users'),
            Column::key('token')->length(512),
            Column::key('ip')->length(24),
            Column::key('domain')->length(100),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('token_id')->number(),
            Field::key('token')->text()->sanitize(false),
            Field::key('ip')->text(),
            Field::key('domain')->text()->sanitize(false),
        ];
    }
}
