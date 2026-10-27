<?php
use Wonder\Elements\Components\Alert;
use Wonder\View\View;
$account_panel ??= new \Wonder\Auth\Frontend\AccountPanel();
$errors = array_values(array_filter(array_map('strval', (array) ($errors ?? []))));
$notice = trim((string) ($notice ?? ''));
View::layout($account_panel->parentLayout());
// Font e stili della pagina vanno nel <head>, non nel corpo: il layout padre li stampa lì.
View::head((string) ($head ?? ''));
?>
<main id="account-page">
    <section class="intro">
        <div class="content">
            <div class="w-100 d-grid col-5 col-t-4 col-p-1 gap-6">
                <aside class="col-1 min-w-0">
                    <?=View::component('frontend.account.navigation', ['items' => $navigation ?? [], 'logout_url' => $logout_url ?? '', 'logout_token' => $logout_token ?? ''])?>
                </aside>
                <div class="col-4 col-t-2 col-p-1 min-w-0 p-5 b-10">
                    <h2 class="p-r f-start w-100 subtitle"><?=e((string) ($title ?? ''))?></h2>
                    <div class="w-100 tx-gray bb-1 mh-4"></div>
                    <?php if ($notice !== ''): ?><?=Alert::make($notice, 'success')->title((string) __t('account.notice_title'))->attr('data-wi-alert-timeout', \Wonder\Auth\Frontend\AccountModal::ALERT_TIMEOUT)->render()?><?php endif; ?>
                    <?php if ($errors !== []): ?><?=Alert::make(implode("\n", $errors), 'error')->title((string) __t('account.error_title'))->attr('data-wi-alert-timeout', \Wonder\Auth\Frontend\AccountModal::ALERT_TIMEOUT)->render()?><?php endif; ?>
                    <div class="w-100">
                        <?=$PAGE_CONTENT?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php foreach ($modals ?? [] as $modal): ?><?=$modal->render()?><?php endforeach; ?>
<?php View::end(); ?>
