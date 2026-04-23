<?php

namespace Wonder\App\Resources\Log;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class MailLogResource extends Resource
{
    public static string $model = \Wonder\App\Models\Log\MailLog::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'email',
            'plural_label' => 'email',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'usata',
            'empty' => 'non usata',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'to_email' => 'Email',
            'subject' => 'Oggetto',
            'service' => 'Servizio',
            'status' => 'Stato',
            'from_email' => 'Mittente',
            'reply_to_email' => 'Risposta',
            'template' => 'Template',
            'body_text' => 'Messaggio',
            'attachments' => 'Allegati',
            'error_message' => 'Errori',
            'ip' => 'IP',
            'user_agent' => 'Browser',
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('to_email')->text()->link('view'),
            TableColumn::key('subject')->text(),
            TableColumn::key('service')->badge()->function('mailService', 'service', 'automaticResize')->size('little'),
            TableColumn::key('status')->badge()->function('mailLogStatus', 'status', 'automaticResize')->size('little'),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->hideButtonAdd()
            ->filters()
            ->searchFields(['from_email', 'reply_to_email', 'to_email', 'subject'])
            ->filterRadio('Servizio', 'service', [
                '' => 'Tutti',
                'phpmailer' => 'PHPMailer',
                'brevo' => 'Brevo',
            ])
            ->filterRadio('Stato', 'status', [
                '' => 'Tutti',
                'sent' => 'Inviata',
                'failed' => 'Fallita',
            ]);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['list', 'view'])
            ->view('show', static::customShowViewPath())
            ->title('view', 'Dettaglio email');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'view'], ['admin', 'administrator']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Log', 'log', 'bi-ear')
            ->title('Email')
            ->order(20)
            ->authority(['admin', 'administrator']);
    }

    private static function customShowViewPath(): ?string
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp !== ''
            ? $rootApp.'/view/pages/backend/log/email/show.php'
            : null;
    }
}
