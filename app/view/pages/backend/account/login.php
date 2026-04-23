<?php \Wonder\View\View::layout('backend.auth'); ?>
<?php
$renderInput = static function (string $key, mixed $value = null) use ($FORM_SCHEMA): string {
    $field = clone $FORM_SCHEMA[$key];
    if ($value !== null) {
        $field->value($value);
    }
    return $field->render();
};
?>
<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <wi-card>

        <div class="d-grid col-12 mx-auto">
            <img id="be-logo-black" src="<?=$DEFAULT->BeLogoBlack?>" class="position-relative w-75 start-50 translate-middle-x d-none" ><img id="be-logo-white" src="<?=$DEFAULT->BeLogoWhite?>" class="position-relative w-75 start-50 translate-middle-x d-none">
        </div>
        
        <div class="col-12">
            <?=$renderInput('username', $fieldUsername ?? '')?>
        </div>

        <div class="col-12">
            <?=$renderInput('password')?>
        </div>

        <div class="d-grid col-8 mx-auto">
            <?=submit('Accedi', 'login'); ?>
        </div>

    </wi-card>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=__r('backend.account.password.recovery')?>">Recupera password</a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
