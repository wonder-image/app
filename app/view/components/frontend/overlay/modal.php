<?php

$id ??= 'wi-modal';
$title ??= '';
$body ??= '';
$closeUrl ??= '#';
$closeLabel ??= 'Chiudi';
$footer ??= null;
$class ??= '';
$attributes ??= '';

?>
<section id="<?=e($id)?>" class="wi-modal no-interaction<?= $class !== '' ? ' '.e($class) : '' ?>"<?= $attributes !== '' ? ' '.$attributes : '' ?>>

    <div class="bg wi-close-modal"></div>

    <div class="content wi-modal-content">
        <div class="wi-modal-header">
            <div class="wi-modal-title"><?=e($title)?></div>
            <div class="wi-modal-close wi-close-modal"> <i class="bi bi-x-lg"></i> </div>
        </div>
        <div class="wi-modal-body"><?= $body ?></div>
    </div>

    <div class="wi-modal-footer b-0">
        <?php
            if ($footer !== null) {
                echo $footer;
            } else {
                echo \Wonder\View\View::component('ui.buttons', [
                    'label' => $closeLabel,
                    'href' => $closeUrl,
                    'class' => 'wi-close-modal',
                    'iconClass' => 'bi bi-chevron-right',
                ]);
            }
        ?>
    </div>

</section>
