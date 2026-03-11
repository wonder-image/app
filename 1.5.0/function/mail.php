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
        ]
    ];

    function mailService($service = null)
    {

        global $MAIL_SERVICE;

        return arrayDetails($MAIL_SERVICE, $service);

    }

    function mailLogStatus($status = null)
    {

        global $MAIL_LOG_STATUS;

        return arrayDetails($MAIL_LOG_STATUS, $status);

    }

    function sendMail($from, $to, $object, $body, $attachments = null, $template = 'basic'){

        global $SOCIETY;

        $SOCIETY_NAME = sanitizeEcho($SOCIETY->name);

        $MAIL = new PHPMailer\PHPMailer\PHPMailer(true);
        $CREDENTIALS = Wonder\App\Credentials::mail();

        $BODY_RAW = emailTemplate($body, $template);
        $BODY_TEXT = htmlToText($BODY_RAW);
        $MAIL_SENT = false;
        $MAIL_ERROR = '';
        $mailService = $CREDENTIALS->service;

        // TODO: quando sarà integrato Brevo, aggiungere qui il ramo dedicato.
        $MAIL_SERVICE_SENT = ($mailService === 'brevo') ? 'phpmailer' : $mailService;

        try {

            # Impostazioni server
                $MAIL->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_OFF; 
                $MAIL->isSMTP();
                $MAIL->Host = $CREDENTIALS->host;
                $MAIL->SMTPAuth = true;
                $MAIL->Username = $CREDENTIALS->username;
                $MAIL->Password = $CREDENTIALS->password;
                $MAIL->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $MAIL->Port = $CREDENTIALS->port;
            
            # Header
                $MAIL->addAddress($to);
                $MAIL->setFrom($CREDENTIALS->username, $SOCIETY_NAME);
                $MAIL->addReplyTo($from, $SOCIETY_NAME);
        
            # Allegati
                if ($attachments != null) {
                    if (is_array($attachments)) {
                        if (count($attachments) >= 1) {
                            foreach ($attachments as $key => $attachment) {

                                if (is_numeric($key)) {
                                    $attachmentName = '';
                                    $attachmentRelativePath = $attachment;
                                } else {
                                    $attachmentName = $attachment;
                                    $attachmentRelativePath = $key;
                                }

                                $MAIL->addAttachment($attachmentRelativePath, $attachmentName);

                            }
                        }
                    } else {
                        
                        $MAIL->addAttachment($attachments);

                    }
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

            $MAIL_SENT = true;

        } catch (PHPMailer\PHPMailer\Exception $e) {

            $MAIL_ERROR = $e->getMessage();
            __log($e, '['.$MAIL_SERVICE_SENT.']', '[sendMail]');

        } finally {

            try {

                $VALUES = Wonder\App\Table::key('mail_log')
                    ->prepare([
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'from_email' => (string) ($CREDENTIALS->username ?? ''),
                        'reply_to_email' => (string) $from,
                        'to_email' => (string) $to,
                        'subject' => (string) $object,
                        'template' => (string) $template,
                        'body_raw' => $BODY_RAW,
                        'body_text' => $BODY_RAW,
                        'attachments' => $attachments,
                        'service' => $MAIL_SERVICE_SENT,
                        'status' => $MAIL_SENT ? 'sent' : 'failed',
                        'error_message' => $MAIL_ERROR,
                        'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    ]);
                    
                sqlInsert('mail_log', $VALUES);

            } catch (Throwable $e) {

                __log($e, '[mail_log]', '[sendMail]');

            }

        }

        return $MAIL_SENT;

    }

    function emailTemplate($body = '', $template = 'basic') {

        global $PATH;
        global $SOCIETY;

        $RETURN = "";
        
        $body = sanitizeEcho($body);

        if ($template == 'basic') {

            $RETURN = '
            <html style="font-family:\'Roboto\', sans-serif;-webkit-text-size-adjust:100%;background-color:#ffffff;color:#414042;font-size:14px;">
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="x-apple-disable-message-reformatting">
                    <style type="text/css">
                        @import url("https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap");
                    </style>
                </head>
                <body style="margin:0;padding:0;border:0;font-family:\'Roboto\', sans-serif;-webkit-text-size-adjust:100%;background-color:#ffffff;color:#414042;font-size:14px;">
                    <div class="container" style="margin:0;padding:0;border:0;position:relative;width:calc(100% - 40px);max-width:800px;margin:0 auto;">
                        <div class="header row" style="margin:0;padding:0;border:0;position:relative;float:left;width:calc(100% - 20px);padding:20px 10px;">
                            <img src="'.$PATH->logo.'" style="margin:0;padding:0;border:0;position: relative;float: left;width: auto;height: 40px;">
                        </div>
                        <div class="line" style="margin:0;padding:0;border:0;position:relative;float:left;width:100%;height:1px;background:#e5e4e2;"></div>
                        <div class="body row" style="margin:0;padding:0;border:0;position:relative;float:left;width:calc(100% - 20px);padding:20px 10px;">
                        '.$body.'
                        </div>
                        <div class="line" style="margin:0;padding:0;border:0;position:relative;float:left;width:100%;height:1px;background:#e5e4e2;"></div>
                        <div class="footer row" style="margin:0;padding:0;border:0;position:relative;float:left;width:calc(100% - 20px);padding:20px 10px;font-size:12px;text-align:center;">
                            <font>Copyright © '.$SOCIETY->legal_name.'. Tutti i diritti riservati.</font>
                        </div>
                    </div>
                </body>
            </html>';

        }

        return $RETURN;

    }
