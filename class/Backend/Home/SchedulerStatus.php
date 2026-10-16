<?php

namespace Wonder\Backend\Home;

use Wonder\App\Resources\Scheduler\DashboardResource;
use Wonder\App\Resources\Scheduler\ScheduleResource;
use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Support\HomeStatus;
use Wonder\Elements\Components\StatusCard;

/** Stato dello scheduler, per chi vede l'elenco delle pianificazioni. */
class SchedulerStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!ScheduleResource::allows($authorities, 'list')) {
            return null;
        }

        $stats = DashboardResource::statistics(1);
        $failures = (int) array_sum(array_column($stats, 'runs')) - (int) array_sum(array_column($stats, 'successes'));

        return HomeStatus::scheduler(
            DashboardResource::heartbeat(),
            $failures,
            time(),
            rtrim($backendUrl, '/').'/'.ScheduleResource::path().'/'
        );
    }

    public function order(): int
    {
        return 10;
    }
}
