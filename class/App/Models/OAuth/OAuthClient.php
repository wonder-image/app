<?php

namespace Wonder\App\Models\OAuth;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class OAuthClient extends Model
{
    public static string $table = 'oauth_clients';
    public static string $folder = 'app/log/oauth';
    public static string $icon = 'bi bi-plug';

    public static function tableSchema(): array
    {
        return [
            Column::key('client_id')->length(64)->null(false)->unique(),
            Column::key('client_name')->length(100)->null(false),
            Column::key('redirect_uris')->json()->null(false),
            Column::key('token_endpoint_auth_method')->length(30)->null(false),
            Column::key('client_secret_hash')->length(64)->null(),
            Column::key('registration_ip')->length(45)->null(),
            Column::key('last_used_at')->datetime()->null(),
            Column::key('revoked_at')->datetime()->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        // `creation` è la colonna d'audit aggiunta dal framework (audit_columns di default): niente `created_at` duplicato.
        return ['idx_oauth_clients_ip' => ['index' => ['registration_ip', 'creation']]];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('client_id')->text()->required(),
            Field::key('client_name')->text()->required(),
            Field::key('redirect_uris')->text()->json()->sanitize(false),
            Field::key('token_endpoint_auth_method')->text()->required(),
            Field::key('client_secret_hash')->text(),
            Field::key('registration_ip')->text(),
            Field::key('last_used_at')->text(),
            Field::key('revoked_at')->text(),
        ];
    }
}
