<?php

    function createQrCode($data, $src = null) {

        $options = new chillerlan\QRCode\QROptions;
        $options->outputType = chillerlan\QRCode\Output\QROutputInterface::CUSTOM;
        $options->outputInterface = chillerlan\QRCode\Output\QRGdImagePNG::class; 
        $options->imageTransparent = false;

        $qrcode = new chillerlan\QRCode\QRCode($options);

        if ($src == null) {
            return $qrcode->render($data);
        } else {
            $qrcode->render($data, $src);
        }

    }