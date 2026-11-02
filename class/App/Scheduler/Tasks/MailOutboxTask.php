<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Scheduler\AbstractTask;
use Wonder\App\Scheduler\Context;
use Wonder\App\Support\Mail\DatabaseOutboxStore;
use Wonder\App\Support\Mail\Outbox;

/** Spedisce le email accodate da `queueMail()`: vedi `Outbox`. */
class MailOutboxTask extends AbstractTask
{
    public function key(): string { return 'wonder.mail-outbox'; }

    public function label(): string { return 'Coda email'; }

    public function expression(): string { return '* * * * *'; }

    public function enabled(): bool { return true; }

    public function alwaysEnabled(): bool { return true; }

    public function run(Context $context): array
    {
        $context->checkDeadline();

        if (!sqlTableExists('mail_log') || !sqlColumnExists('mail_log', 'payload')) {
            return ['skipped' => 'mail_log_outbox_missing'];
        }

        return Outbox::process(new DatabaseOutboxStore(), 'mailDeliver', $context);
    }
}
