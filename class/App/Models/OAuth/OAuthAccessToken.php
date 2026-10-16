<?php

namespace Wonder\App\Models\OAuth;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class OAuthAccessToken extends Model
{
    public static string $table = 'oauth_access_tokens';
    public static string $folder = 'app/log/oauth';
    public static string $icon = 'bi bi-key';

    public static function tableSchema(): array
    {
        return [
            Column::key('grant_id')->int()->null(false),
            Column::key('selector')->length(16)->null(false)->unique(),
            Column::key('validator_hash')->length(64)->null(false),
            Column::key('expires_at')->datetime()->null(false),
            Column::key('revoked_at')->datetime()->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'idx_oauth_at_grant' => ['index' => ['grant_id']],
            'idx_oauth_at_expires' => ['index' => ['expires_at']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('grant_id')->number()->required(),
            Field::key('selector')->text()->required(),
            Field::key('validator_hash')->text()->required(),
            Field::key('expires_at')->text()->required(),
            Field::key('revoked_at')->text(),
        ];
    }
}
