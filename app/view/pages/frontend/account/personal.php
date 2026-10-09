<?php
use Wonder\View\View;
$title ??= '';
$active ??= 'personal';
$account_panel->layout(compact('title', 'active', 'navigation', 'errors', 'notice', 'modals', 'logout_url', 'logout_token', 'head', 'user'));
?>
<?php foreach ((array) ($rows ?? []) as $row): ?>
    <?=View::component('frontend.account.row', (array) $row)?>
<?php endforeach; ?>
<?php View::end(); ?>
