<?php

use Wonder\App\Resources\OAuth\ConnectionsResource;
use Wonder\Http\Csrf;
use Wonder\View\View;

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$user = \Wonder\App\LegacyGlobals::get('USER');
$user = is_object($user) ? $user : null;
$servers = ConnectionsResource::servers();
$rows = ConnectionsResource::rows($user);
$seeAll = ConnectionsResource::seeAll($user);

View::layout('backend.show', ['TITLE' => __t('oauth.connections.title'), 'SUBTITLE' => '']);
?>
<section>
    <h2><?= $e(__t('oauth.connections.servers_title')) ?></h2>
    <?php if ($servers === null): ?>
        <div class="alert alert-warning"><?= $e(__t('oauth.connections.issuer_error')) ?></div>
    <?php else: ?>
        <p><?= $e(__t('oauth.connections.servers_help')) ?></p>
        <?php foreach ($servers as $r): $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $r['name'])), '-') ?: 'mcp'; ?>
            <div class="card border mb-3"><div class="card-body">
                <h3 class="h6"><?= $e($r['name']) ?></h3>
                <p><code><?= $e($r['url']) ?></code>
                    <button type="button" class="btn btn-sm btn-light" data-copy="<?= $e($r['url']) ?>"><?= $e(__t('oauth.connections.copy')) ?></button></p>
                <p class="mb-0"><?= $e(__t('oauth.connections.claude_code_example')) ?>:
                    <code>claude mcp add --transport http <?= $e($slug) ?> <?= $e($r['url']) ?></code></p>
            </div></div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section>
    <h2><?= $e(__t('oauth.connections.active_title')) ?></h2>
    <?php if ($rows === []): ?>
        <p><?= $e(__t('oauth.connections.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive"><table class="table">
            <thead><tr>
                <?php if ($seeAll): ?><th><?= $e(__t('oauth.connections.col_user')) ?></th><?php endif; ?>
                <th><?= $e(__t('oauth.connections.col_client')) ?></th>
                <th><?= $e(__t('oauth.connections.col_redirect')) ?></th>
                <th><?= $e(__t('oauth.connections.col_resource')) ?></th>
                <th><?= $e(__t('oauth.connections.col_consented')) ?></th>
                <th><?= $e(__t('oauth.connections.col_last_used')) ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php if ($seeAll): ?><td><?= $e($row['user_name']) ?></td><?php endif; ?>
                    <td><?= $e($row['client_name']) ?></td>
                    <td><?= $e($row['redirect_host']) ?></td>
                    <td><?= $e($row['resource']) ?></td>
                    <td><?= $e($row['consented_at']) ?></td>
                    <td><?= $e($row['last_used_at'] ?? __t('oauth.connections.never')) ?></td>
                    <td><form method="post"><?= Csrf::field()->render() ?>
                        <input type="hidden" name="grant_id" value="<?= (int) $row['grant_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><?= $e(__t('oauth.connections.revoke')) ?></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.dataset.copy).then(function () { b.textContent = <?= json_encode(__t('oauth.connections.copied'), JSON_HEX_TAG | JSON_HEX_AMP) ?>; });
    });
});
</script>
<?php View::end(); ?>
