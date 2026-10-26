<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.main');
?>
<?php
$renderProfileInput = static function (string $key, mixed $value = null) use ($PROFILE_FORM_SCHEMA, $VALUES): string {
    $field = clone $PROFILE_FORM_SCHEMA[$key];
    if ($value !== null) {
        $field->value($value);
    } elseif (array_key_exists($key, (array) $VALUES)) {
        $field->value($VALUES[$key]);
    }
    return $field->render();
};
$renderPasswordInput = static function (string $key, mixed $value = null) use ($PASSWORD_FORM_SCHEMA): string {
    $field = clone $PASSWORD_FORM_SCHEMA[$key];
    if ($value !== null) {
        $field->value($value);
    }
    return $field->render();
};
?>
<div class="row g-3">

    <?php ob_start(); ?>
        <h3>Impostazioni account</h3>
    <?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>

    <form class="col-9" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()" data-wi-save-bar<?=!empty($PROFILE_DIRTY) ? ' data-wi-save-bar-dirty' : ''?>>
        <?=\Wonder\Http\Csrf::field()->render()?>
        <?php ob_start(); ?>
            <div class="col-12">
                <h6>Modifica dati</h6>
            </div>
                <div class="col-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <?=$renderProfileInput('profile_picture')?>
                        </div>
                    </div>
                </div>
                <div class="col-9">
                    <div class="row g-3">
                        <div class="col-5">
                            <?=$renderProfileInput('name')?>
                        </div>
                        <div class="col-5">
                            <?=$renderProfileInput('surname')?>
                        </div>
                        <div class="col-6">
                            <?=$renderProfileInput('username')?>
                        </div>
                        <div class="col-6">
                            <?=$renderProfileInput('phone')?>
                        </div>
                        <div class="col-4">
                            <?=$renderProfileInput('color')?>
                        </div>
                        <div class="col-6">
                            <?=$renderProfileInput('email')?>
                        </div>
                        <div class="col-6">
                            <?=$renderProfileInput('password', '')?>
                        </div>
                    </div>
                </div>
            <div class="col-12">
                <?=submit('Modifica dati', 'modify'); ?>
            </div>
        <?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>
    </form>

    <form class="col-3" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <?=\Wonder\Http\Csrf::field()->render()?>
            <?php ob_start(); ?>
                <div class="col-12">
                    <h6>Modifica password</h6>
                </div>
                <div class="col-12">
                    <?=$renderPasswordInput('old-password', '')?>
                </div>
                <div class="col-12">
                    <?=$renderPasswordInput('new-password', '')?>
                </div>
            <div class="col-12">
                <?=submit('Modifica password', 'modify-password'); ?>
            </div>
        <?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>
    </form>

</div>
<?php \Wonder\View\View::end(); ?>
