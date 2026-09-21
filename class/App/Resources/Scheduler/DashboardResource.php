<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

class DashboardResource extends NavigationOnlyResource
{
    public static function path(): string { return 'app/scheduler'; }
    public static function icon(): string { return 'bi-clock'; }
    public static function titleLabel(): string { return 'Riepilogo'; }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->section('dev', 'Dev', 'bi-terminal', 1025, ['admin', 'administrator'])->title('Riepilogo')->order(10)->authority(['admin']);
    }
}
