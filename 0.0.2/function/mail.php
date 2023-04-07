<?php

    function sendMail($from, $to, $object, $content){

        global $SOCIETY;
        global $PATH;

        // Imposto gli headers
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=utf-8';
            $headers[] = "From: $SOCIETY->name <$from>";

        // Testo
            $text = "
            <!DOCTYPE html>
            <html lang='it'>
            <head>
                <meta charset=UTF-8'>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>$object</title>
            </head>
            <body>
                
                <div class='container' style='@import url(https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap);font-family: Roboto, sans-serif;position: relative;float: left;width: 90%;max-width: 500px;border: 1px solid #e5e4e2;left: 50%;transform: translateX(-50%);margin-top: 2%;background: #ffffff;color: #000000;'>

                    <div class='logo' style='position: relative;float: left;width: 90%;padding: 5%;'>
                        <img src='$PATH->logo' alt='$SOCIETY->name' style='position: relative;float: left;width: 30%;'>
                    </div>

                    <div class='text' style='position: relative;float: left;width: 90%;padding: 5%;padding-top: 0;font-size: 14px;line-height: 17px;'>
                        $content
                    </div>

                    <div class='line' style='position: relative;float: left;width: 100%;height: 1px;background: #e5e4e2;'></div>

                    <div class='text' style='position: relative;float: left;width: 90%;padding: 5%;text-align: center;font-size: 12px;line-height: 12px;'>
                        Copyright © $SOCIETY->legal_name . Tutti i diritti riservati.
                    </div>

                </div>

                <div class='signature' style='position: relative;float: left;width: 96%;margin-top: 5%;padding: 2%;border-top: 1px solid #ccc;color: #555;font-size: 12px;'>
                    Questa è un'email automatica, il servizio è stato fornito da <a href='https://www.wonderimage.it/' style='color: black;text-decoration: underline;'>Wonder Image</a>.
                </div>

            </body>
            </html>
            ";
        
        // Invio email
            if (mail($to, $object, $text, implode("\r\n", $headers))) {
                return true;
            }else{
                return false;
            }

        // Fine
    
    }

?>