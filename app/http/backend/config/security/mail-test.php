<?php

use Wonder\App\Resources\Config\SecurityResource;
use Wonder\App\Support\Mail\CredentialsTest;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;
use Wonder\App\LegacyGlobals;

$back = SecurityResource::editUrl();

if (!Csrf::verify()) {
    FlashAlert::custom('Sessione scaduta', 'Ricarica la pagina e riprova.', 'danger');
} else {
    // Prova i valori del modal (SMTP o Brevo) senza salvarli.
    $service = ($_POST['integration'] ?? '') === 'brevo-mail' ? 'brevo' : 'phpmailer';
    $credentials = CredentialsTest::fromForm($service, $_POST);
    $user = $USER ?? LegacyGlobals::get('USER');
    $result = CredentialsTest::run((string) ($user->email ?? ''), $credentials);
    // Il messaggio contiene testo del server SMTP remoto: va escapato.
    FlashAlert::custom($result->title, e($result->message), $result->ok ? 'success' : 'danger');
}

header('Location: '.$back, true, 303);
exit;
