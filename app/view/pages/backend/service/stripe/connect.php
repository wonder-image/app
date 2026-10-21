<?php \Wonder\View\View::layout('backend.auth'); ?>
<form method="post" action="<?=e($ACTION)?>">
    <?=\Wonder\Http\Csrf::field()->render()?>
    <input type="hidden" name="account" value="<?=e($ACCOUNT)?>">
    <wi-card>

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

    </wi-card>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=e($CANCEL)?>"><?=e(__t('stripe.connect.cancel'))?></a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
