<?php

    function createQrCode($data, $src = null, $quality = 30, $quietzone = 4, $rounded = false) {

        $customOptions = [
            'version'         => chillerlan\QRCode\Common\Version::AUTO,
            'eccLevel'        => chillerlan\QRCode\Common\EccLevel::L,
            'outputType'      => chillerlan\QRCode\Output\QROutputInterface::CUSTOM,
            'outputInterface' => Wonder\Plugin\QrCode\QRGdRounded::class,
            'imageTransparent'=> false,
            'scale'           => $quality,
            'quietzoneSize'   => $quietzone
        ];
        
        if ($rounded) {
            $customOptions['outputInterface'] = Wonder\Plugin\QrCode\QRGdRounded::class;
        } else {
            $customOptions['outputInterface'] = chillerlan\QRCode\Output\QRGdImagePNG::class;
        }

        $options = new chillerlan\QRCode\QROptions($customOptions);
        $qrcode = new chillerlan\QRCode\QRCode($options);

        if ($src == null) {
            return $qrcode->render($data);
        } else {
            $qrcode->render($data, $src);
        }

    }
