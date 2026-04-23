<?php

namespace Wonder\App\Models\Log;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class MailLog extends Model
{
    public static string $table = 'mail_log';
    public static string $folder = 'app/log/email';
    public static string $icon = 'bi bi-envelope';

    public static function tableSchema(): array
    {
        return [
            Column::key('user_id')->int()->foreign('user'),
            Column::key('from_email')->length(255),
            Column::key('reply_to_email')->length(255),
            Column::key('to_email')->length(255),
            Column::key('subject')->length(1000),
            Column::key('template')->length(100),
            Column::key('body_raw')->type('LONGTEXT'),
            Column::key('body_text')->type('LONGTEXT'),
            Column::key('attachments')->json(),
            Column::key('service')->length(50)->default('phpmailer'),
            Column::key('status')->length(20),
            Column::key('error_message')->type('LONGTEXT'),
            Column::key('request_uri'),
            Column::key('ip')->length(45),
            Column::key('user_agent')->length(255),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_status' => [
                'index' => 'status',
            ],
            'ind_service' => [
                'index' => 'service',
            ],
            'ind_user_id' => [
                'index' => 'user_id',
            ],
            'ind_to_status' => [
                'index' => ['to_email', 'status'],
            ],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('user_id')->number(),
            Field::key('from_email')->text(),
            Field::key('reply_to_email')->text(),
            Field::key('to_email')->text(),
            Field::key('subject')->text(),
            Field::key('template')->text(),
            Field::key('body_raw')->text(),
            Field::key('body_text')->text()->htmlToText(),
            Field::key('attachments')->text(),
            Field::key('service')->text(),
            Field::key('status')->text(),
            Field::key('error_message')->text(),
            Field::key('request_uri')->text(),
            Field::key('ip')->text(),
            Field::key('user_agent')->text(),
        ];
    }
}
