<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.auth');
?>
<form method="post" action="<?=e(__r('backend.account.logout'))?>">
    <?=\Wonder\Http\Csrf::field()->render()?>
    <?php ob_start(); ?>

        <div class="col-12 text-center">
            <p class="mb-0"><?=e(__t('auth.logout.confirm'))?></p>
        </div>

        <div class="d-grid col-8 mx-auto">
            <button type="submit" class="btn btn-dark"><?=e(__t('auth.logout.submit'))?></button>
        </div>

    <?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=e(__r('backend.home'))?>"><?=e(__t('auth.logout.cancel'))?></a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
