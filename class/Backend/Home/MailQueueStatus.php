<?php

namespace Wonder\Backend\Home;

use Wonder\App\Resources\Log\MailLogResource;
use Wonder\App\Support\Mail\DatabaseOutboxStore;
use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Support\HomeStatus;
use Wonder\Elements\Components\StatusCard;

/** Coda email ferma, per chi vede il registro email; quando scorre non appare. */
class MailQueueStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!MailLogResource::allows($authorities, 'list') || !sqlColumnExists('mail_log', 'payload')) {
            return null;
        }

        return HomeStatus::mailQueue(
            (new DatabaseOutboxStore())->oldestDue(),
            time(),
            rtrim($backendUrl, '/').'/'.MailLogResource::path().'/'
        );
    }

    public function order(): int
    {
        return 30;
    }
}
