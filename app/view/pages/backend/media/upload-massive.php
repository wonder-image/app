<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

\Wonder\View\View::layout('backend.main');

$submitHtml = function_exists('submit')
    ? submit()
    : '<button type="submit" class="btn btn-dark w-100">Salva</button>';

$layout = (new Form)->components([

    (new Card)->components([
        (clone $FORM_SCHEMA['file'])->columnSpan(12),
    ])->columns(12)->columnSpan(9),

    (new Card)->components([
        (clone $FORM_SCHEMA['type'])->columnSpan(12),
        '<div class="col-12">'.$submitHtml.'</div>',
    ])->columns(12)->columnSpan(3),

])->columns(12);
?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3>
            <?php if (!empty($BACK_URL)) { ?>
            <a href="<?=htmlspecialchars((string) $BACK_URL, ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
            <?php } ?>
            <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
        </h3>
    </wi-card>

    <?= ResourceFormLayoutRenderer::render($layout, [
        'action' => '',
        'method' => 'POST',
        'enctype' => 'multipart/form-data',
    ]) ?>
</div>

<?php \Wonder\View\View::end(); ?>
