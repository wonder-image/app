<?php
use Wonder\Http\Csrf;
use Wonder\View\View;

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$vars = ['client' => $request['client_name'], 'resource' => $request['resource_name'], 'site' => $siteName, 'host' => $redirectHost,
    'name' => trim(($user->name ?? '').' '.($user->surname ?? '')) ?: ($user->email ?? '')];

View::layout('backend.auth');
?>
<form method="post" action="/oauth/consent?req=<?= $e(rawurlencode($reqId)) ?>">
    <wi-card>
        <h1 class="tx-center"><?= $e(__t('oauth.consent.title', $vars)) ?></h1>
        <p><?= $e(__t('oauth.consent.intro', $vars)) ?></p>
        <p><?= $e(__t('oauth.consent.redirect_to', $vars)) ?></p>
        <?php if ($loopback): ?>
            <p class="tx-warning"><strong><?= $e(__t('oauth.consent.loopback_warning')) ?></strong></p>
        <?php endif; ?>
        <?= Csrf::field()->render() ?>
        <input type="hidden" name="req" value="<?= $e($reqId) ?>">
        <button type="submit" name="action" value="approve" class="btn btn-success"><?= $e(__t('oauth.consent.approve')) ?></button>
        <button type="submit" name="action" value="deny" class="btn btn-secondary"><?= $e(__t('oauth.consent.deny')) ?></button>
        <p>
            <?= $e(__t('oauth.consent.signed_in_as', $vars)) ?> ·
            <button type="submit" name="action" value="switch" class="btn-link"><?= $e(__t('oauth.consent.switch_account')) ?></button>
        </p>
    </wi-card>
</form>
<?php View::end(); ?>
