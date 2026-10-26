<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.list');
?>

<?php if (!empty($READONLY)) { ?>
<?php ob_start(); ?>
    <div class="alert alert-warning mb-0"><?=htmlspecialchars((string) ($READONLY_NOTICE ?? ''), ENT_QUOTES, 'UTF-8')?></div>
<?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([ob_get_clean()]))?>
<?php } ?>

<?=$TABLE_HTML?>

<?php \Wonder\View\View::end(); ?>
