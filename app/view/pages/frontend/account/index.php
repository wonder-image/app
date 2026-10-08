<?php
use Wonder\View\View;
$title ??= '';
$active ??= 'overview';
$account_panel->layout(compact('title', 'active', 'navigation', 'errors', 'notice', 'modals', 'logout_url', 'logout_token', 'head', 'user'));
?>
<p><?=str_replace('%NAME%', '<strong>'.e((string) $user->name).'</strong>', e((string) __t('account.overview.greeting', ['name' => '%NAME%'])))?></p>
<p><?=e((string) __t('account.overview.code'))?> <strong><?=e((string) ($contact['code'] ?? ''))?></strong></p>
<?php foreach ((array) ($rows ?? []) as $row): ?>
    <?=View::component('frontend.account.row', (array) $row)?>
<?php endforeach; ?>
<?php View::end(); ?>
