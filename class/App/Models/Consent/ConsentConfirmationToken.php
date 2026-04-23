<?php

namespace Wonder\App\Models\Consent;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ConsentConfirmationToken extends Model
{
    public static string $table = 'consent_confirmation_tokens';
    public static string $folder = 'app/log/consent';
    public static string $icon = 'bi bi-envelope-check';

    public static function tableSchema(): array
    {
        return [
            Column::key('token_type')->length(50)->null(false),
            Column::key('user_id')->int()->null(false)->foreign('user'),
            Column::key('token')->length(128)->null(false)->unique(),
            Column::key('language_code')->length(2)->null(),
            Column::key('continue_url')->type('LONGTEXT')->null(),
            Column::key('metadata_json')->json()->null(),
            Column::key('expires_at')->datetime()->null(false),
            Column::key('confirmed_at')->datetime()->null(),
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
            'idx_user_expires' => [
                'index' => ['user_id', 'token_type', 'expires_at'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('token_type')->text()->required(),
            Field::key('user_id')->number()->required(),
            Field::key('token')->text()->required(),
            Field::key('language_code')->text(),
            Field::key('continue_url')->text(),
            Field::key('metadata_json')->text()->json()->sanitize(false),
            Field::key('expires_at')->text()->required(),
            Field::key('confirmed_at')->text(),
            Field::key('revoked_at')->text(),
            Field::key('created_at')->text(),
        ];
    }
}
