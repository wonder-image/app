<?php

    function createQrCode($data, $src = null) {

        $options = new chillerlan\QRCode\QROptions;   
        $options->imageTransparent = false;

        $qrcode = new chillerlan\QRCode\QRCode($options);
        $qrcode->render($data, $src);

        return $qrcode;

    }

?>