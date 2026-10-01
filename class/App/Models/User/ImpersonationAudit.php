<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class ImpersonationAudit extends Model
{
    public static string $table = 'auth_impersonation_audits';
    public static string $folder = 'app/log/impersonation';
    public static string $icon = 'bi bi-person-bounding-box';

    public static function tableSchema(): array
    {
        return [
            Column::key('actor_user_id')->int()->null()->foreign('user'),
            Column::key('subject_user_id')->int()->null()->foreign('user'),
            Column::key('event')->length(30)->null(false),
            Column::key('ip')->length(64)->null(),
            Column::key('user_agent')->length(255)->null(),
            Column::key('metadata_json')->json()->null(),
            Column::key('created_at')->datetime()->null(false)->default('CURRENT_TIMESTAMP'),
        ];
    }

    public static function tableOptions(): array
    {
        return ['audit_columns' => false];
    }

    public static function tablePseudos(): array
    {
        return [
            'idx_impersonation_actor' => ['index' => ['actor_user_id', 'created_at']],
            'idx_impersonation_subject' => ['index' => ['subject_user_id', 'created_at']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('actor_user_id')->number(),
            Field::key('subject_user_id')->number(),
            Field::key('event')->text()->required(),
            Field::key('ip')->text(),
            Field::key('user_agent')->text(),
            Field::key('metadata_json')->text()->json()->sanitize(false),
            Field::key('created_at')->text(),
        ];
    }
}
