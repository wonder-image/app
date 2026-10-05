<?php

namespace Wonder\Auth\Frontend;

use Wonder\View\View;

/** Presentation/extension policy; contact storage and payments remain provider-owned. */
class AccountPanel
{
    public function parentLayout(): string { return 'frontend.main'; }
    public function title(): string { return (string) __t('account.title'); }
    public function navigation(object $user): array { return []; }
    public function personalFields(array $fields, object $user): array { return $fields; }
    public function validatePersonal(array $input, object $user): array { return []; }
    public function personalUserValues(array $input, object $user): array { return []; }
    public function afterPersonalSaved(array $input, object $user): void {}
    public function overviewRows(array $rows, object $user): array { return $rows; }

    public function layout(array $data = []): void
    {
        View::layout('frontend.account.panel', $data + ['account_panel' => $this]);
    }
}
