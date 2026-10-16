<?php

namespace Wonder\Backend\Home;

use Wonder\App\Resources\System\ErrorReportResource;
use Wonder\App\Support\Errors\ErrorReporter;
use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Backend\Support\HomeStatus;
use Wonder\Elements\Components\StatusCard;

/** Errori aperti, per chi vede l'elenco delle segnalazioni. */
class ErrorStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!ErrorReportResource::allows($authorities, 'list')) {
            return null;
        }

        return HomeStatus::errors(
            count(ErrorReporter::open()),
            rtrim($backendUrl, '/').'/'.ErrorReportResource::path().'/'
        );
    }

    public function order(): int
    {
        return 20;
    }
}
