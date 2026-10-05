<?php

use Wonder\Auth\Frontend\AuthValidationAlert;

$validationAlert = AuthValidationAlert::make(
    (array) ($errors ?? []),
    $alert ?? null,
    isset($federated_error) ? (string) $federated_error : null,
    isset($validation_messages) ? (array) $validation_messages : null,
);
?>
<?php if ($validationAlert !== null): ?>
    <?=$validationAlert->render()?>
<?php endif; ?>
