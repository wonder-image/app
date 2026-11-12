<?php

namespace Wonder\Backend\Home;

use Wonder\App\Media\ResizeQueue;
use Wonder\App\Resources\Scheduler\ScheduleResource;
use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Support\HomeStatus;
use Wonder\Elements\Components\StatusCard;

/**
 * Ridimensionamenti fermi o falliti, per chi vede l'elenco delle
 * pianificazioni; quando la coda scorre non appare.
 */
class MediaResizeStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!ScheduleResource::allows($authorities, 'list')) {
            return null;
        }

        $store = ResizeQueue::store();

        if (!$store->usable()) {
            return null;
        }

        $now = time();

        return HomeStatus::mediaResize(
            $store->oldestPending(),
            $store->failed(ResizeQueue::time($now - ResizeQueue::FAILED_WINDOW), ResizeQueue::MISSING),
            $now,
            rtrim($backendUrl, '/').'/'.ScheduleResource::path().'/'
        );
    }

    public function order(): int
    {
        return 40;
    }
}
