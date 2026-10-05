<?php
use Wonder\Elements\Components\Alert;
use Wonder\View\View;
$title ??= '';
$active ??= 'overview';
$errors = array_values(array_filter(array_map('strval', (array) ($errors ?? []))));
$notice = trim((string) ($notice ?? ''));
$account_panel ??= new \Wonder\Auth\Frontend\AccountPanel();
View::layout($account_panel->parentLayout());
?>
<main id="account-page">
    <?php if ($notice !== ''): ?><?=Alert::make($notice, 'success')->title((string) __t('account.notice_title'))->render()?><?php endif; ?>
    <?php if ($errors !== []): ?><?=Alert::make(implode("\n", $errors), 'error')->title((string) __t('account.error_title'))->render()?><?php endif; ?>
    <section class="intro">
        <div class="content">
            <h1 class="title mb-6"><?=e($account_panel->title())?></h1>
            <div class="d-grid col-5 col-p-1 gap-6" style="align-items:start;">
                <aside class="col-1" style="min-width:0;">
                    <?=View::component('frontend.account.navigation', [
                        'items' => $account_panel->navigation($user), 'active' => $active,
                        'csrf_token' => $csrf_token, 'logout_url' => $logout_url ?? '',
                    ])?>
                </aside>
                <div class="col-4 col-p-1" style="min-width:0;">
                    <?php if ($title !== ''): ?><h2 class="subtitle pb-4 mb-5" style="border-bottom:1px solid var(--tx-color-10,var(--input-border-color));"><?=e($title)?></h2><?php endif; ?>
                    <?=$PAGE_CONTENT?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php foreach ($page_modals ?? [] as $modal): ?><?=$modal->render()?><?php endforeach; ?>
<?php View::end(); ?>
