<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Scheduler\AbstractTask;
use Wonder\App\Scheduler\Context;
use Wonder\OAuth\Cleanup;
use Wonder\OAuth\OAuth;

class OAuthCleanupTask extends AbstractTask
{
    public function key(): string { return 'wonder.oauth_cleanup'; }

    public function label(): string { return 'Pulizia token OAuth'; }

    public function expression(): string { return '30 3 * * *'; }

    public function enabled(): bool { return true; }

    public function run(Context $context): array
    {
        $context->checkDeadline();

        if (!sqlTableExists('oauth_grants')) {
            return ['skipped' => 'oauth_tables_missing'];
        }

        return Cleanup::run(OAuth::store(), time());
    }
}
