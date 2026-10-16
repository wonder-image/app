<?php

namespace Wonder\App\Models\OAuth;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class OAuthGrant extends Model
{
    public static string $table = 'oauth_grants';
    public static string $folder = 'app/log/oauth';
    public static string $icon = 'bi bi-shield-check';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->null(false)->foreign('user'),
            Column::key('client_id')->length(64)->null(false),
            Column::key('resource')->length(255)->null(false),
            Column::key('scope')->length(255)->null(false)->default(''),
            Column::key('consented_at')->datetime()->null(false),
            Column::key('last_used_at')->datetime()->null(),
            Column::key('revoked_at')->datetime()->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return ['idx_oauth_grants_owner' => ['index' => ['user_id', 'client_id', 'resource']]];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('client_id')->text()->required(),
            Field::key('resource')->text()->required(),
            Field::key('scope')->text(),
            Field::key('consented_at')->text()->required(),
            Field::key('last_used_at')->text(),
            Field::key('revoked_at')->text(),
        ];
    }
}
