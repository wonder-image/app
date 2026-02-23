<?php

function alert() {

    global $ALERT;

    if (empty($ALERT) && !empty($_GET['alert'])) {
        $ALERT = $_GET['alert'];
    }

    if (!empty($ALERT)) {
        echo "alertToast($ALERT);";
    }

}

function alertTheme($code, $type = null, $title = null, $text = null): string
{

    $code = (string) $code;

    if ($code !== 'custom') {
        $type = __t("notifications.{$code}.type");
        $title = __t("notifications.{$code}.title");
        $text = __t("notifications.{$code}.text");
    }

    $level = strtolower(trim((string) $type));
    $level = match ($level) {
        'error', 'danger' => 'error',
        'success' => 'success',
        'warning' => 'warning',
        default => 'info',
    };

    return (new \Wonder\Elements\Components\Alert)
        ->title((string) ($title ?? ''))
        ->message((string) ($text ?? ''))
        ->level($level)
        ->dismissible(true)
        ->render();
}
