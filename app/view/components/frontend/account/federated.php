<?php
$googleUrl = $auth_profile->route('federated', ['provider' => 'google']);
$auth_surface ??= 'login';
if ($google_client_id === '') { return; }
?>
<div class="w-100 mt-6">
    <p class="text-small a-c"><?=e(__t('auth.federated.separator'))?></p>
    <div class="d-grid col-1 gap-3 mt-3 w-100">
        <div id="account-google-signin" class="d-flex j-content-center w-100"></div>
    </div>
</div>
<script>
(() => {
    const googleContainer = document.getElementById('account-google-signin');

    const postToken = token => {
        const form = document.createElement('form');
        form.id = <?=js_e($auth_surface === 'signup' ? 'google_sign_up' : 'google_login')?>;
        form.method = 'post';
        form.action = <?=js_e($googleUrl)?>;
        const values = {
            credential: token,
            csrf_token: <?=js_e($csrf_token)?>,
            auth_surface: <?=js_e($auth_surface)?>
        };
        document.querySelectorAll('#login [name], #sign_up [name]').forEach(source => {
            const name = source.name;
            if (['password', 'password_confirmation', 'csrf_token'].includes(name) || name.startsWith('g-recaptcha-')) return;
            if (source.type !== 'checkbox' || source.checked) values[name] = source.value;
        });
        Object.entries(values).forEach(([name, value]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input);
        });
        document.body.appendChild(form); form.submit();
    };

    const googleScript = document.createElement('script');
    googleScript.src = 'https://accounts.google.com/gsi/client';
    googleScript.async = true;
    googleScript.defer = true;
    googleScript.onload = () => {
        const googleButtonWidth = Math.floor(googleContainer.getBoundingClientRect().width);
        google.accounts.id.initialize({
            client_id: <?=js_e($google_client_id)?>,
            nonce: <?=js_e($oidc_nonce)?>,
            callback: response => postToken(response.credential)
        });
        google.accounts.id.renderButton(
            googleContainer,
            {theme: 'outline', size: 'large', width: googleButtonWidth}
        );
    };
    document.head.appendChild(googleScript);
})();
</script>
