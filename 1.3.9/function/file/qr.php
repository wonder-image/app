<?php

    function createQrCode($data, $src = null) {

        $options = new chillerlan\QRCode\QROptions;   

        $options->version = 3;
        $options->eccLevel = chillerlan\QRCode\Common\EccLevel::M;
        $options->outputType = chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
        $options->scale = 20;
        $options->quality = 100;
        $options->quietzoneSize = 2;
        $options->outputBase64 = true;
        $options->imageTransparent = false;

        $qrcode = new chillerlan\QRCode\QRCode($options);
        $qrcode->render($data, $src);

        return $qrcode;

    }