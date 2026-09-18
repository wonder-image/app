<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

class DashboardResource extends NavigationOnlyResource
{
    public static function path(): string { return 'app/scheduler'; }
    public static function icon(): string { return 'bi-clock'; }
    public static function titleLabel(): string { return 'Riepilogo cron'; }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->inSection('scheduler')->title('Riepilogo')->order(1)->authority(['admin']);
    }
}
