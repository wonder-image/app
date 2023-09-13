<?php

    function sendMail($from, $to, $object, $body, $attachment = null, $template = 'basic'){

        global $SOCIETY;

        $SOCIETY_NAME = sanitizeEcho($SOCIETY->name);

        // Genera un separatore univoco per il tipo di contenuto "multipart/mixed"
            $boundary = "==Multipart_Boundary_x".md5(time())."x";

        // Intestazioni dell'email
            $headers = "From: $SOCIETY_NAME <$from>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"".$boundary."\"\r\n";

        // Contenuto HTML
            $message = "--$boundary\n";
            $message .= "Content-Type: text/html; charset=\"UTF-8\"\n";
            $message .= "Content-Transfer-Encoding: 7bit\n\n";

            $message .= emailTemplate($body, $template)."\n";

        // Allegati
            if ($attachment != null) {
                if (is_array($attachment)) {
                    if (count($attachment) >= 1) {
                        foreach ($attachment as $key => $attachmentRelativePath) {

                            // Nome del file allegato
                            $attachmentName = basename($attachmentRelativePath);

                            // Dimensione del file allegato
                            $attachmentSize = filesize($attachmentRelativePath);

                            // Apri il file allegato
                            $attachmentFile = fopen($attachmentRelativePath, "rb");

                            // Leggi il contenuto del file allegato
                            $attachmentContent = fread($attachmentFile, $attachmentSize);

                            // Chiudi il file allegato
                            fclose($attachmentFile);

                            // Ottieni il tipo MIME del file allegato
                            $attachmentType = getMimeType($attachmentName);

                            // Codifica il contenuto del file allegato in base64
                            $attachmentContent = chunk_split(base64_encode($attachmentContent));

                            // Aggiungi l'allegato all'email
                            $message .= "--$boundary\n";
                            $message .= "Content-Type: $attachmentType; name=\"$attachmentName\"\n";
                            $message .= "Content-Description: $attachmentName\n";
                            $message .= "Content-Disposition: attachment;\n filename=\"$attachmentName\"; size=\"$attachmentSize\";\n";
                            $message .= "Content-Transfer-Encoding: base64\n\n$attachmentContent\n\n";

                        }
                    }
                } else {

                    $attachmentRelativePath = $attachment;

                    // Nome del file allegato
                    $attachmentName = basename($attachmentRelativePath);

                    // Dimensione del file allegato
                    $attachmentSize = filesize($attachmentRelativePath);

                    // Apri il file allegato
                    $attachmentFile = fopen($attachmentRelativePath, "rb");

                    // Leggi il contenuto del file allegato
                    $attachmentContent = fread($attachmentFile, $attachmentSize);

                    // Chiudi il file allegato
                    fclose($attachmentFile);

                    // Ottieni il tipo MIME del file allegato
                    $attachmentType = getMimeType($attachmentName);

                    // Codifica il contenuto del file allegato in base64
                    $attachmentContent = chunk_split(base64_encode($attachmentContent));

                    // Aggiungi l'allegato all'email
                    $message .= "--$boundary\n";
                    $message .= "Content-Type: $attachmentType; name=\"$attachmentName\"\n";
                    $message .= "Content-Description: $attachmentName\n";
                    $message .= "Content-Disposition: attachment;\n filename=\"$attachmentName\"; size=\"$attachmentSize\";\n";
                    $message .= "Content-Transfer-Encoding: base64\n\n$attachmentContent\n\n";

                }
            }
        
        // Chiudi contenuto
            $message .= "--$boundary--";

        
        // Invio email
            if (mail($to, $object, $message, $headers)) {
                return true;
            }else{
                return false;
            }

        // Fine
    
    }
    function emailTemplate($body = '', $template = 'basic') {

        global $PATH;
        global $SOCIETY;

        $RETURN = "";
        
        $body = sanitizeEcho($body);

        if ($template == 'basic') {

            $RETURN = "
            <html>
                <head>
        
                    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <meta name='x-apple-disable-message-reformatting'>
        
                    <style type='text/css'>
                    </style>
                    
                    <!--[if !mso]><!--><link href='https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap' rel='stylesheet' type='text/css'><!--<![endif]-->
        
                </head>
                <body style='margin: 0;padding: 0;-webkit-text-size-adjust: 100%;background-color: #ffffff;color: #414042'>
                    <div class='container' style='font-family: Roboto, sans-serif;position: relative;float: left;width: calc(100% - 40px);max-width: 500px;left: 50%;transform: translateX(-50%);background: #ffffff;color: #414042;font-size: 14px;'>
                        
                        <div class='row' style='position: relative;float: left;width: calc(100% - 20px); padding: 20px 10px;'>
                            <img src='$PATH->logo' style='position: relative;float: left;width: auto;height: 40px;'>
                        </div>
        
                        <div class='line' style='position: relative;float: left;width: 100%; height: 1px;background: #e5e4e2;'></div>
        
                        <div id='email-body' class='row' style='position: relative;float: left;font-family: Roboto, sans-serif;width: calc(100% - 20px); padding: 20px 10px;'>
                            $body
                        </div>
        
                        <div class='line' style='position: relative;float: left;width: 100%; height: 1px;background: #e5e4e2;'></div>
        
                        <div class='row' style='position: relative;float: left;font-family: Roboto, sans-serif;width: calc(100% - 20px); padding: 20px 10px; text-align: center;font-size: 12px;'>
                            Copyright © $SOCIETY->legal_name. Tutti i diritti riservati.
                        </div>
        
                    </div>
                </body>
            </html>";

        }

        return $RETURN;

    }

?>