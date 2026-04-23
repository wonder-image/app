<?php \Wonder\View\View::layout('backend.auth'); ?>
<?php
$renderInput = static function (string $key, mixed $value = null) use ($FORM_SCHEMA, $VALUES): string {
    $field = clone $FORM_SCHEMA[$key];
    if ($value !== null) {
        $field->value($value);
    } elseif (array_key_exists($key, (array) $VALUES)) {
        $field->value($VALUES[$key]);
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
            <?=$renderInput('password')?>
        </div>

        <div class="d-grid col-8 mx-auto">
            <?=submit('Imposta la password', 'set-password'); ?>
        </div>
    </wi-card>
</form>

<div class="row mt-3">
    <div class="col-12 text-center">
        <a class="text-dark" href="<?=__r('backend.account.login')?>">Accedi</a>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
