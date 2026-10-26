<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.list');
?>

<?php if (!empty($READONLY)) { ?>
<?=ResourceFormLayoutRenderer::renderLayout((new Card)->components([
    \Wonder\Elements\Components\Alert::make((string) ($READONLY_NOTICE ?? ''), 'warning')->inline()->addClass('mb-0')->render(),
]))?>
<?php } ?>

<?=$TABLE_HTML?>

<?php \Wonder\View\View::end(); ?>
