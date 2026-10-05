<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.signup.complete_title'), 'text' => (string) __t('auth.signup.step_two')]);
?>
<?=View::component('frontend.account.auth-form', [
    'auth_profile' => $auth_profile, 'surface' => 'signup-completion', 'form_id' => 'sign_up_completion',
    'fields' => $fields, 'csrf_token' => $csrf_token, 'submit_key' => 'auth.signup.complete_submit',
    'token' => $token,
])?>
<?php View::end(); ?>
