<?php
use Wonder\View\View;

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
View::layout('backend.auth');
?>
<wi-card>
    <h1 class="tx-center"><?= $e(__t($titleKey)) ?></h1>
    <p class="tx-center"><?= $e(__t($messageKey)) ?></p>
</wi-card>
<?php View::end(); ?>
