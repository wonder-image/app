<?php \Wonder\View\View::layout('backend.auth'); ?>
<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <wi-card>
        <div class="d-grid col-12 mx-auto">
            <img id="be-logo-black" src="<?=$DEFAULT->BeLogoBlack?>" class="position-relative w-75 start-50 translate-middle-x d-none" ><img id="be-logo-white" src="<?=$DEFAULT->BeLogoWhite?>" class="position-relative w-75 start-50 translate-middle-x d-none">
        </div>

        <div class="col-12">
            <?=text('Username', 'username', 'disabled', $USER->username ?? ''); ?>
        </div>

        <div class="col-12">
            <?=password('Nuova password', 'password', 'required'); ?>
        </div>

        <div class="d-grid col-8 mx-auto">
            <?=submit('Cambia password', 'restore'); ?>
        </div>
    </wi-card>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=__r('backend.account.login')?>">Accedi</a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
