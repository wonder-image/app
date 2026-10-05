<?php
use Wonder\View\View;
$auth_profile->layout(['title' => (string) __t('auth.login.title')]);
?>
<?=View::component('frontend.account.auth-form', [
    'auth_profile' => $auth_profile, 'surface' => 'login', 'form_id' => 'login',
    'fields' => $fields, 'csrf_token' => $csrf_token, 'submit_key' => 'auth.login.submit',
    
])?>

<?=View::component('frontend.account.federated', compact('auth_profile', 'csrf_token', 'oidc_nonce', 'google_client_id') + ['auth_surface' => 'login'])?>
<?php $continue = (string) ($_POST['continue'] ?? $_GET['continue'] ?? ''); ?>
<div class="text-small a-c mt-6"><a href="<?=e($auth_profile->route('signup.request').($continue !== '' ? '?continue='.rawurlencode($continue) : ''))?>"><?=e(__t('auth.login.signup'))?></a></div>
<?php View::end(); ?>
