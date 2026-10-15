<?php
use Wonder\Http\Csrf;
use Wonder\View\View;

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$vars = ['client' => $request['client_name'], 'resource' => $request['resource_name'], 'site' => $siteName];

View::layout('backend.auth');
?>
<form method="post" action="/oauth/login?req=<?= $e(rawurlencode($reqId)) ?>">
    <wi-card>
        <h1 class="tx-center"><?= $e(__t('oauth.login.title', $vars)) ?></h1>
        <p class="tx-center"><?= $e(__t('oauth.login.subtitle', $vars)) ?></p>
        <?php if ($alert !== null) { echo alertTheme($alert); } ?>
        <?= Csrf::field()->render() ?>
        <input type="hidden" name="req" value="<?= $e($reqId) ?>">
        <div class="col-12">
            <label><?= $e(__t('oauth.login.username')) ?>
                <input type="text" name="username" autocomplete="username" required autofocus>
            </label>
        </div>
        <div class="col-12">
            <label><?= $e(__t('oauth.login.password')) ?>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
        </div>
        <div class="d-grid col-8 mx-auto">
            <button type="submit" class="btn btn-success"><?= $e(__t('oauth.login.submit')) ?></button>
        </div>
    </wi-card>
</form>
<?php View::end(); ?>
