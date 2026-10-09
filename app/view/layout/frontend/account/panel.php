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
            <div class="wi-side-layout">
                <aside class="wi-side-layout__aside">
                    <?=View::component('frontend.account.navigation', ['items' => $navigation ?? [], 'logout_url' => $logout_url ?? '', 'logout_token' => $logout_token ?? ''])?>
                </aside>
                <div class="wi-side-layout__main">
                    <h2 class="subtitle wi-side-layout__title"><?=e((string) ($title ?? ''))?></h2>
                    <?php if ($notice !== ''): ?><?=Alert::make($notice, 'success')->title((string) __t('account.notice_title'))->render()?><?php endif; ?>
                    <?php if ($errors !== []): ?><?=Alert::make(implode("\n", $errors), 'error')->title((string) __t('account.error_title'))->render()?><?php endif; ?>
                    <?=$PAGE_CONTENT?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php foreach ($modals ?? [] as $modal): ?><?=$modal->render()?><?php endforeach; ?>
<?php View::end(); ?>
