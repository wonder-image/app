<?php

namespace Wonder\App\Models\Consent;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class UserConsentState extends Model
{
    public static string $table = 'user_consent_state';
    public static string $folder = 'app/log/consent';
    public static string $icon = 'bi bi-check2-square';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->null(false)->foreign('user'),
            Column::key('consent_type')->length(120)->null(false),
            Column::key('current_status')->enum(['accepted', 'rejected', 'withdrawn', 'pending'])->null(false),
            Column::key('legal_document_id')->int()->null()->foreign('legal_documents'),
            Column::key('last_event_id')->int()->null(false)->foreign('consent_events'),
            Column::key('updated_at')->datetime()->null(false)->default('CURRENT_TIMESTAMP')->schema('on_update', 'CURRENT_TIMESTAMP'),
        ];
    }

    public static function tableOptions(): array
    {
        return [
            'auto_id' => false,
            'audit_columns' => false,
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'pk_user_consent_type' => [
                'primary' => ['user_id', 'consent_type'],
            ],
            'idx_consent_type_status' => [
                'index' => ['consent_type', 'current_status'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('consent_type')->text()->required(),
            Field::key('current_status')->text()->required(),
            Field::key('legal_document_id')->number(),
            Field::key('last_event_id')->number()->required(),
            Field::key('updated_at')->text(),
        ];
    }
}
