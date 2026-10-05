<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.recovery.title'), 'text' => (string) __t($sent ? 'auth.recovery.sent' : 'auth.recovery.text')]);
?>
<?php if (!$sent): ?>
<?=View::component('frontend.account.auth-form', [
    'auth_profile' => $auth_profile, 'surface' => 'password-recovery', 'form_id' => 'password_recovery',
    'fields' => $fields, 'csrf_token' => $csrf_token, 'submit_key' => 'auth.recovery.submit',
    
])?>
<?php endif; ?>
<p class="text-small a-c mt-6"><a href="<?=e($auth_profile->route('login'))?>"><?=e(__t('auth.back_login'))?></a></p>
<?php View::end(); ?>
