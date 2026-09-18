<?php

// `$surface` dice dove sta girando la pagina: nel frontend il JavaScript
// della lib accetta solo il codice della notifica, nel backend anche un
// avviso scritto al momento.
function alert(string $surface = 'frontend') {

    global $ALERT;

    if (empty($ALERT) && !empty($_GET['alert'])) {
        $ALERT = $_GET['alert'];
    }

    // Nello script solo codici numerici: i messaggi testuali li mostra il form
    // e il valore di `?alert=` non deve poter iniettare codice.
    if (!empty($ALERT) && is_numeric($ALERT)) {
        echo 'alertToast('.(int) $ALERT.');';
    }

    // Avviso messo in coda prima di un redirect, tipico del salvataggio.
    echo \Wonder\Backend\Support\FlashAlert::script($surface === 'backend');

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
