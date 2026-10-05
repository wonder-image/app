<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.restore.title')]);
?>
<?=View::component('frontend.account.auth-form', [
    'auth_profile' => $auth_profile, 'surface' => 'password-restore', 'form_id' => 'password_restore',
    'fields' => $fields, 'csrf_token' => $csrf_token, 'submit_key' => 'auth.restore.submit',
    'token' => $token,
])?>
<p class="text-small a-c mt-6"><a href="<?=e($auth_profile->route('login'))?>"><?=e(__t('auth.back_login'))?></a></p>
<?php View::end(); ?>
