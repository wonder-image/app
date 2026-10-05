<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.signup.title'), 'text' => (string) __t('auth.signup.step_one')]);
?>
<?=View::component('frontend.account.auth-form', [
    'auth_profile' => $auth_profile, 'surface' => 'signup-request', 'form_id' => 'sign_up',
    'fields' => $fields, 'csrf_token' => $csrf_token, 'submit_key' => 'auth.signup.submit',
    
])?>

<?=View::component('frontend.account.federated', compact('auth_profile', 'csrf_token', 'oidc_nonce', 'google_client_id') + ['auth_surface' => 'signup'])?>
<?php $continue = (string) ($_POST['continue'] ?? $_GET['continue'] ?? ''); ?>
<div class="text-small a-c mt-6"><a href="<?=e($auth_profile->route('login').($continue !== '' ? '?continue='.rawurlencode($continue) : ''))?>"><?=e(__t('auth.signup.login'))?></a></div>
<?php View::end(); ?>
