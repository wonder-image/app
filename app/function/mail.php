<?php

    $MAIL_SERVICE = [
        'phpmailer' => [
            'name' => 'PHPMailer',
            'text' => 'PHPMailer (SMTP)',
            'icon' => 'bi bi-envelope',
            'color' => 'info'
        ],
        'brevo' => [
            'name' => 'Brevo',
            'text' => 'Brevo API',
            'icon' => 'bi bi-send',
            'color' => 'primary'
        ]
    ];

    $MAIL_LOG_STATUS = [
        'sent' => [
            'name' => 'Inviata',
            'text' => 'Email inviata con successo',
            'icon' => 'bi bi-check-circle',
            'color' => 'success'
        ],
        'failed' => [
            'name' => 'Fallita',
            'text' => 'Invio email non riuscito',
            'icon' => 'bi bi-exclamation-octagon',
            'color' => 'danger'
        ],
        'queued' => [
            'name' => 'In coda',
            'text' => 'In attesa del prossimo giro dello scheduler',
            'icon' => 'bi bi-hourglass-split',
            'color' => 'secondary'
        ],
        'sending' => [
            'name' => 'In invio',
            'text' => 'Presa in carico dallo scheduler',
            'icon' => 'bi bi-send',
            'color' => 'info'
        ]
    ];

    function mailService($service = null)
    {

        global $MAIL_SERVICE;

        if (!is_array($MAIL_SERVICE ?? null) || $MAIL_SERVICE === []) {
            $MAIL_SERVICE = [
                'phpmailer' => [
                    'name' => 'PHPMailer',
                    'text' => 'PHPMailer (SMTP)',
                    'icon' => 'bi bi-envelope',
                    'color' => 'info'
                ],
                'brevo' => [
                    'name' => 'Brevo',
                    'text' => 'Brevo API',
                    'icon' => 'bi bi-send',
                    'color' => 'primary'
                ]
            ];
        }

        return arrayDetails($MAIL_SERVICE, $service);

    }

    function mailLogStatus($status = null)
    {

        global $MAIL_LOG_STATUS;

        if (!is_array($MAIL_LOG_STATUS ?? null) || $MAIL_LOG_STATUS === []) {
            $MAIL_LOG_STATUS = [
                'sent' => [
                    'name' => 'Inviata',
                    'text' => 'Email inviata con successo',
                    'icon' => 'bi bi-check-circle',
                    'color' => 'success'
                ],
                'failed' => [
                    'name' => 'Fallita',
                    'text' => 'Invio email non riuscito',
                    'icon' => 'bi bi-exclamation-octagon',
                    'color' => 'danger'
                ],
                'queued' => [
                    'name' => 'In coda',
                    'text' => 'In attesa del prossimo giro dello scheduler',
                    'icon' => 'bi bi-hourglass-split',
                    'color' => 'secondary'
                ],
                'sending' => [
                    'name' => 'In invio',
                    'text' => 'Presa in carico dallo scheduler',
                    'icon' => 'bi bi-send',
                    'color' => 'info'
                ]
            ];
        }

        return arrayDetails($MAIL_LOG_STATUS, $status);

    }

    /**
     * `$credentials` sostituisce `Credentials::mail()` per questo invio (la
     * prova del backend). `$options`: `cc` e `bcc`, stringa o lista di
     * indirizzi (vedi `Wonder\App\Support\Mail\Recipients`); passalo per
     * nome: `sendMail($from, $to, $oggetto, $corpo, options: ['cc' => $copia])`.
     */
    function sendMail($from, $to, $object, $body, $attachments = null, $template = 'basic', ?object $credentials = null, array $options = []){

        // I test girano sul database di un sito vero, con indirizzi veri:
        // lì non parte niente.
        if (defined('WONDER_NO_MAIL') && WONDER_NO_MAIL === true) {
            return true;
        }

        // Un `MailMessage` diventa stringa qui: `mail_log` e coda salvano testo.
        $body = (string) $body;

        $DELIVERY = mailDeliver($from, $to, $object, $body, $attachments, $template, $credentials, $options);

        $GLOBALS['MAIL_LAST_ERROR'] = $DELIVERY['error'];

        mailLogInsert([
            'from_email' => $DELIVERY['from_email'],
            'reply_to_email' => $DELIVERY['reply_to_email'],
            'to_email' => (string) $to,
            'cc_email' => Wonder\App\Support\Mail\Recipients::format($DELIVERY['cc']),
            'bcc_email' => Wonder\App\Support\Mail\Recipients::format($DELIVERY['bcc']),
            'subject' => (string) $object,
            'template' => (string) $template,
            'body_raw' => $DELIVERY['body_raw'],
            'body_text' => $DELIVERY['body_text'],
            'attachments' => $attachments,
            'service' => $DELIVERY['service'],
            'status' => $DELIVERY['sent'] ? 'sent' : 'failed',
            'error_message' => $DELIVERY['error'],
        ]);

        return $DELIVERY['sent'];

    }

    /**
     * Come `sendMail()`, ma la mail entra nella coda (`mail_log` con stato
     * `queued`) e parte al prossimo giro del task `wonder.mail-outbox`.
     * Senza heartbeat recente dello scheduler, o se la coda non è
     * scrivibile, spedisce subito con `sendMail()`. Vedi
     * `Wonder\App\Support\Mail\Outbox`. `$options` come in `sendMail()`
     * (`cc`, `bcc`): passalo per nome, `options: [...]`.
     */
    function queueMail($from, $to, $object, $body, $attachments = null, $template = 'basic', array $options = []){

        if (defined('WONDER_NO_MAIL') && WONDER_NO_MAIL === true) {
            return true;
        }

        return Wonder\App\Support\Mail\Outbox::queue([
            'from' => (string) $from,
            'to' => (string) $to,
            'subject' => (string) $object,
            'body' => (string) $body,
            'attachments' => $attachments,
            'template' => (string) $template,
            'options' => $options,
        ]);

    }

    /** Allegati di `sendMail()` come lista di `['path' => ..., 'name' => ...]`. */
    function mailAttachments($attachments): array
    {

        $attachmentList = [];

        if ($attachments == null) {
            return $attachmentList;
        }

        if (!is_array($attachments)) {
            return [[ 'path' => $attachments, 'name' => '' ]];
        }

        foreach ($attachments as $key => $attachment) {

            if (is_numeric($key)) {
                $attachmentName = '';
                $attachmentRelativePath = $attachment;
            } else {
                $attachmentName = $attachment;
                $attachmentRelativePath = $key;
            }

            $attachmentList[] = [
                'path' => $attachmentRelativePath,
                'name' => $attachmentName
            ];

        }

        return $attachmentList;

    }

    /**
     * Spedisce una mail con il servizio configurato, senza scrivere
     * `mail_log`: lo fanno `sendMail()` (riga nuova) e la coda (riga già
     * accodata). Ritorna esito, servizio, errore e i dati da registrare,
     * compresi i `cc` e `bcc` normalizzati. `$options` come in `sendMail()`.
     */
    function mailDeliver($from, $to, $object, $body, $attachments = null, $template = 'basic', ?object $credentials = null, array $options = []): array
    {

        global $SOCIETY;

        $BODY_RAW = '';
        $BODY_TEXT = '';
        $MAIL_SENT = false;
        $MAIL_ERROR = '';
        $CREDENTIALS = null;
        $replyTo = trim((string) $from);
        $MAIL_SERVICE_SENT = 'phpmailer';
        // Una chiave sconosciuta in `$options` è un errore di chi chiama: eccezione, non invio fallito.
        $RECIPIENTS = Wonder\App\Support\Mail\Recipients::options($options, $to);

        try {

            $SOCIETY_NAME = sanitizeEcho($SOCIETY->name);

            $MAIL = new PHPMailer\PHPMailer\PHPMailer(true);
            $CREDENTIALS = $credentials ?? Wonder\App\Credentials::mail();

            $BODY_RAW = emailTemplate((string) $body, $template);
            $BODY_TEXT = htmlToText($BODY_RAW);
            $mailService = strtolower((string) ($CREDENTIALS->service ?? 'phpmailer'));

            if (!in_array($mailService, [ 'phpmailer', 'brevo' ])) {
                $mailService = 'phpmailer';
            }

            $attachmentList = mailAttachments($attachments);

            $MAIL_SERVICE_SENT = $mailService;

            switch ($mailService) {

                case 'brevo':

                    if (empty($CREDENTIALS->brevo_api_key)) {
                        throw new Exception('Brevo API Key non configurata');
                    }

                    $BREVO = Wonder\Plugin\Brevo\TransactionalEmail::connect($CREDENTIALS->brevo_api_key);

                    $BREVO->sender($CREDENTIALS->username, $SOCIETY_NAME)
                        ->to($to);

                    foreach ($RECIPIENTS['cc'] as $address) { $BREVO->cc($address); }
                    foreach ($RECIPIENTS['bcc'] as $address) { $BREVO->bcc($address); }

                    if ($replyTo !== '') {
                        $BREVO->replyTo($replyTo, $SOCIETY_NAME);
                    }

                    $BREVO->subject($object)
                        ->html($BODY_RAW)
                        ->text($BODY_TEXT);

                    foreach ($attachmentList as $attachment) {

                        $attachmentPath = (string) ($attachment['path'] ?? '');
                        $attachmentName = (string) ($attachment['name'] ?? '');

                        if (empty($attachmentPath)) {
                            continue;
                        }

                        if (!is_file($attachmentPath) || !is_readable($attachmentPath)) {
                            throw new Exception('Allegato non trovato o non leggibile: '.$attachmentPath);
                        }

                        $attachmentContent = file_get_contents($attachmentPath);

                        if ($attachmentContent === false) {
                            throw new Exception('Impossibile leggere l\'allegato: '.$attachmentPath);
                        }

                        if (empty($attachmentName)) {
                            $attachmentName = basename($attachmentPath);
                        }

                        $BREVO->attachmentContent($attachmentName, base64_encode($attachmentContent));

                    }

                    $BREVO->send();
                    break;

                default:

                    # Impostazioni server (condivise con la verifica dal backend)
                        Wonder\App\Support\Mail\SmtpTransport::configure($MAIL, $CREDENTIALS);
                    
                    # Header
                        $MAIL->addAddress($to);
                        foreach ($RECIPIENTS['cc'] as $address) { $MAIL->addCC($address); }
                        foreach ($RECIPIENTS['bcc'] as $address) { $MAIL->addBCC($address); }
                        $MAIL->setFrom($CREDENTIALS->username, $SOCIETY_NAME);
                        if ($replyTo !== '') { $MAIL->addReplyTo($replyTo, $SOCIETY_NAME); }
                
                    # Allegati
                        foreach ($attachmentList as $attachment) {

                            $attachmentPath = (string) ($attachment['path'] ?? '');
                            $attachmentName = (string) ($attachment['name'] ?? '');

                            if (empty($attachmentPath)) {
                                continue;
                            }

                            $MAIL->addAttachment($attachmentPath, $attachmentName);

                        }
                
                    # Body
                        $MAIL->CharSet = 'UTF-8';
                        $MAIL->Encoding = 'base64';
                        $MAIL->isHTML(true);
                        $MAIL->Subject = $object;
                        $MAIL->Body = $BODY_RAW;
                        $MAIL->AltBody = $BODY_TEXT;
                
                    # Invia
                        $MAIL->send();
                    break;

            }

            $MAIL_SENT = true;

        } catch (Throwable $e) {

            $MAIL_ERROR = $e->getMessage();
            __log($e, '['.$MAIL_SERVICE_SENT.']', '[sendMail]');

        }

        return [
            'sent' => $MAIL_SENT,
            'service' => $MAIL_SERVICE_SENT,
            'error' => $MAIL_ERROR,
            'from_email' => (string) ($CREDENTIALS->username ?? ''),
            'reply_to_email' => $replyTo !== '' ? $replyTo : null,
            'body_raw' => $BODY_RAW,
            'body_text' => $BODY_TEXT,
            'cc' => $RECIPIENTS['cc'],
            'bcc' => $RECIPIENTS['bcc'],
        ];

    }

    /**
     * Scrive una riga di `mail_log` con utente e richiesta correnti; ritorna
     * l'id, o null se l'inserimento non riesce (l'errore finisce nel log).
     */
    function mailLogInsert(array $row): ?int
    {

        try {

            $VALUES = Wonder\App\Table::key('mail_log')
                ->prepare(array_merge([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ], $row));

            $RESULT = sqlInsert('mail_log', $VALUES);

            return $RESULT->success ? (int) $RESULT->insert_id : null;

        } catch (Throwable $e) {

            __log($e, '[mail_log]', '[sendMail]');

            return null;

        }

    }

    /** Errore dell'ultimo `sendMail()` ('' se è andato a buon fine o non è mai partito). */
    function sendMailError(): string {
        return (string) ($GLOBALS['MAIL_LAST_ERROR'] ?? '');
    }

    /**
     * Impagina il corpo nel template (`basic`, l'unico): vedi
     * `Wonder\App\Support\Mail\MailLayout`. Un corpo `MailMessage` entra
     * già escapato; una stringa HTML passa da `sanitizeEcho()` come sempre.
     */
    function emailTemplate($body = '', $template = 'basic') {

        return Wonder\App\Support\Mail\MailLayout::render((string) $body, (string) $template);

    }
