<?php \Wonder\View\View::layout('backend.auth'); ?>
<form method="post" action="<?=e(__r('backend.account.logout'))?>">
    <?=\Wonder\Http\Csrf::field()->render()?>
    <wi-card>

        <div class="col-12 text-center">
            <p class="mb-0"><?=e(__t('auth.logout.confirm'))?></p>
        </div>

        <div class="d-grid col-8 mx-auto">
            <button type="submit" class="btn btn-dark"><?=e(__t('auth.logout.submit'))?></button>
        </div>

    </wi-card>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=e(__r('backend.home'))?>"><?=e(__t('auth.logout.cancel'))?></a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
