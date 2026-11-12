<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Media\ResizeQueue;
use Wonder\App\Scheduler\AbstractTask;
use Wonder\App\Scheduler\Context;

/**
 * Genera le misure delle immagini accodate da `uploadFiles()`: vedi
 * `ResizeQueue`. Ogni minuto, per `ResizeQueue::BUDGET` secondi al massimo.
 */
class MediaResizeTask extends AbstractTask
{
    public function key(): string { return 'wonder.media-resize'; }

    public function label(): string { return 'Ridimensionamento immagini'; }

    public function expression(): string { return '* * * * *'; }

    public function enabled(): bool { return true; }

    public function alwaysEnabled(): bool { return true; }

    public function timeout(): int { return 120; }

    public function run(Context $context): array
    {
        $context->checkDeadline();

        return ResizeQueue::drain($context);
    }
}
