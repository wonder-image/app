<?php

namespace Wonder\App\Models\Consent;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ConsentEvent extends Model
{
    public static string $table = 'consent_events';
    public static string $folder = 'app/log/consent';
    public static string $icon = 'bi bi-shield-check';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->null(false)->foreign('user'),
            Column::key('consent_type')->length(120)->null(false),
            Column::key('action')->enum(['accept', 'reject', 'withdraw'])->null(false),
            Column::key('legal_document_id')->int()->null()->foreign('legal_documents'),
            Column::key('occurred_at')->datetime()->null(false),
            Column::key('ip_address')->length(45)->null(false),
            Column::key('user_agent')->length(1000)->null(false),
            Column::key('locale')->length(2)->null(false),
            Column::key('source')->enum(['web', 'app', 'api', 'admin'])->null(false),
            Column::key('ui_surface')->length(120)->null(false),
            Column::key('evidence_json')->json()->null(),
            Column::key('creation')->datetime()->null(false),
        ];
    }

    public static function tableOptions(): array
    {
        return [
            'audit_columns' => true,
            'audit_auto_columns' => false,
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'idx_user_consent_type_time' => [
                'index' => ['user_id', 'consent_type', 'occurred_at'],
            ],
            'idx_legal_document_id' => [
                'index' => 'legal_document_id',
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number()->required(),
            Field::key('consent_type')->text()->required(),
            Field::key('action')->text()->required(),
            Field::key('legal_document_id')->number(),
            Field::key('occurred_at')->text()->required(),
            Field::key('ip_address')->text()->required(),
            Field::key('user_agent')->text()->required(),
            Field::key('locale')->text()->required(),
            Field::key('source')->text()->required(),
            Field::key('ui_surface')->text()->required(),
            Field::key('evidence_json')->text()->json()->sanitize(false),
            Field::key('creation')->text(),
        ];
    }
}
