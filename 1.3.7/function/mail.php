<?php

    function sendMail($from, $to, $object, $body, $attachment = null, $template = 'basic'){

        global $MAIL;
        global $SOCIETY;

        $SOCIETY_NAME = sanitizeEcho($SOCIETY->name);

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {

            # Impostazioni server
                $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_OFF; 
                $mail->isSMTP();
                $mail->Host = $MAIL->host;
                $mail->SMTPAuth = true;
                $mail->Username = $MAIL->username;
                $mail->Password = $MAIL->password;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = $MAIL->port;
            
            # Header
                $mail->setFrom($from, $SOCIETY_NAME);
                $mail->addAddress($to);
                $mail->addReplyTo($from, $SOCIETY_NAME);
        
            # Allegati
                if ($attachment != null) {
                    if (is_array($attachment)) {
                        if (count($attachment) >= 1) {
                            foreach ($attachment as $key => $value) {

                                if (is_numeric($key)) {
                                    $attachmentName = '';
                                    $attachmentRelativePath = $value;
                                } else {
                                    $attachmentName = $value;
                                    $attachmentRelativePath = $key;
                                }

                                $mail->addAttachment($attachmentRelativePath, $attachmentName);

                            }
                        }
                    } else {
                        
                        $mail->addAttachment($attachment);

                    }
                }
        
            # Body
                $mail->isHTML(true);
                $mail->Subject = $object;
                $mail->Body = emailTemplate($body, $template);
                $mail->AltBody = $body;
        
            # Invia
                $mail->send();

            return true;

        } catch (PHPMailer\PHPMailer\Exception $e) {

            return false;

        }
    
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

?>