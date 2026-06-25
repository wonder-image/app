<?php

$id ??= 'loading-spinner';
$message ??= 'Loading...';
$class ??= '';
$attributes ??= '';

?>
<section id="<?=e($id)?>" class="p-f full-page bg-dark-0 d-none no-interaction<?= $class !== '' ? ' '.e($class) : '' ?>" style="z-index: 1100"<?= $attributes !== '' ? ' '.$attributes : '' ?>>
    <div class="bg bg-dark-10 blur-2"></div>
    <div class="p-a center">
        <div class="title a-c">
            <span class="spinner-border"></span> 
        </div>
        <div class="a-c text mt-4">
            <?=e($message)?>
        </div>
    </div>
</section>
