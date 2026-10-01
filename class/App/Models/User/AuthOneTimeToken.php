<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class AuthOneTimeToken extends Model
{
    public static string $table = 'auth_one_time_tokens';
    public static string $folder = 'app/log/auth-tokens';
    public static string $icon = 'bi bi-key';

    public static function tableSchema(): array
    {
        return [
            Column::key('purpose')->length(50)->null(false),
            Column::key('subject_user_id')->int()->null(false)->foreign('user'),
            Column::key('actor_user_id')->int()->null()->foreign('user'),
            Column::key('selector')->length(64)->null(false)->unique(),
            Column::key('validator_hash')->length(64)->null(false),
            Column::key('continue_url')->type('LONGTEXT')->null(),
            Column::key('metadata_json')->json()->null(),
            Column::key('expires_at')->datetime()->null(false),
            Column::key('consumed_at')->datetime()->null(),
            Column::key('revoked_at')->datetime()->null(),
            Column::key('created_at')->datetime()->null(false)->default('CURRENT_TIMESTAMP'),
        ];
    }

    public static function tableOptions(): array
    {
        return [
            'audit_columns' => false,
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'idx_auth_token_subject' => [
                'index' => ['purpose', 'subject_user_id', 'expires_at'],
            ],
            'idx_auth_token_actor' => [
                'index' => ['actor_user_id', 'purpose'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('purpose')->text()->required(),
            Field::key('subject_user_id')->number()->required(),
            Field::key('actor_user_id')->number(),
            Field::key('selector')->text()->required(),
            Field::key('validator_hash')->text()->required(),
            Field::key('continue_url')->text(),
            Field::key('metadata_json')->text()->json()->sanitize(false),
            Field::key('expires_at')->text()->required(),
            Field::key('consumed_at')->text(),
            Field::key('revoked_at')->text(),
            Field::key('created_at')->text(),
        ];
    }
}
