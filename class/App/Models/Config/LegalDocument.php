<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class LegalDocument extends Model
{
    public static string $table = 'legal_documents';
    public static string $folder = 'app/config/legal-documents';
    public static string $icon = 'bi bi-file-earmark-text';

    public static function tableSchema(): array
    {
        return [
            Column::key('doc_type')->length(100)->null(false),
            Column::key('name')->length(255)->null(false)->default(''),
            Column::key('version')->length(50)->null(false),
            Column::key('language_code')->length(2)->null(false),
            Column::key('checkbox_label')->length(255)->null(false),
            Column::key('content_hash')->length(64)->null(false),
            Column::key('content_snapshot')->type('LONGTEXT')->null(false),
            Column::key('published_at')->datetime()->null(false),
            Column::key('active')->length(5)->null(false)->default('true'),
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
            'idx_doc_type_lang_active' => [
                'index' => ['doc_type', 'language_code', 'active'],
            ],
            'uq_doc_type_version_language' => [
                'unique' => ['doc_type', 'version', 'language_code'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('doc_type')->text()->required(),
            Field::key('name')->text(),
            Field::key('version')->text()->required(),
            Field::key('language_code')->text()->required(),
            Field::key('checkbox_label')->text()->required(),
            Field::key('content_hash')->text(),
            Field::key('content_snapshot')->text()->required(),
            Field::key('published_at')->text()->required(),
            Field::key('active')->text()->required(),
        ];
    }
}
