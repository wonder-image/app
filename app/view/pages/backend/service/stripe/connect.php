<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.auth');
?>
<form method="post" action="<?=e($ACTION)?>">
    <?=\Wonder\Http\Csrf::field()->render()?>
    <input type="hidden" name="account" value="<?=e($ACCOUNT)?>">
    <?php ob_start(); ?>

        <div class="col-12 text-center">
            <h1 class="h5"><?=e(__t('stripe.connect.title'))?></h1>
            <p class="mb-0"><?=e($CONFIRM)?></p>
            <?php if (!empty($MESSAGE)) : ?>
            <p class="mt-2 mb-0 text-danger" role="alert"><?=e($MESSAGE)?></p>
            <?php endif; ?>
        </div>

        <div class="d-grid col-8 mx-auto">
            <button type="submit" class="btn btn-dark"><?=e(__t('stripe.connect.submit'))?></button>
        </div>

    <?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=e($CANCEL)?>"><?=e(__t('stripe.connect.cancel'))?></a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
