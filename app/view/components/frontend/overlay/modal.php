<?php

$id ??= 'wi-modal';
$title ??= '';
$body ??= '';
$closeUrl ??= '#';
$closeLabel ??= 'Chiudi';
$footer ??= null;
$class ??= '';
$contentClass ??= '';
$headerClass ??= '';
$titleClass ??= '';
$bodyClass ??= '';
$footerClass ??= 'b-0';
$attributes ??= '';
$showFooter ??= true;

?>
<section id="<?=e($id)?>" class="wi-modal no-interaction<?= $class !== '' ? ' '.e($class) : '' ?>"<?= $attributes !== '' ? ' '.$attributes : '' ?>>

    <div class="bg wi-close-modal"></div>

    <div class="content wi-modal-content<?= $contentClass !== '' ? ' '.e($contentClass) : '' ?>">
        <div class="wi-modal-header<?= $headerClass !== '' ? ' '.e($headerClass) : '' ?>">
            <div class="wi-modal-title<?= $titleClass !== '' ? ' '.e($titleClass) : '' ?>"><?=e($title)?></div>
            <div class="wi-modal-close wi-close-modal"> <i class="bi bi-x-lg"></i> </div>
        </div>
        <div class="wi-modal-body<?= $bodyClass !== '' ? ' '.e($bodyClass) : '' ?>"><?= $body ?></div>
    </div>

    <?php if ($showFooter) { ?>
        <div class="wi-modal-footer<?= $footerClass !== '' ? ' '.e($footerClass) : '' ?>">
            <?php
                if ($footer !== null) {
                    echo $footer;
                } else {
                    echo \Wonder\View\View::component('ui.button', [
                        'label' => $closeLabel,
                        'href' => $closeUrl,
                        'class' => 'wi-close-modal',
                        'iconClass' => 'bi bi-chevron-right',
                    ]);
                }
            ?>
        </div>
    <?php } ?>

</section>
