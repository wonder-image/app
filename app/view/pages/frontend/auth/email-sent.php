<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.email.sent_title'), 'text' => (string) __t('auth.email.sent_text')]);
?>
<p class="text-small a-c mt-6"><a href="<?=e($auth_profile->route('login'))?>"><?=e(__t('auth.back_login'))?></a></p>
<?php View::end(); ?>
