<?php

    function createQrCode($data, $src = null, $quality = 30, $quietzone = 4, $rgb = [0, 0, 0], $rounded = false) {

        $QRCODE = new Wonder\Plugin\QrCode\Generates($data, $src);
        $QRCODE->setQuality($quality);
        $QRCODE->setQuietzone($quietzone);
        $QRCODE->setColor($rgb);
        $QRCODE->setRounded($rounded);
        
        if ($src == null) {
            return $QRCODE->generate();
        } else {
            $QRCODE->generate();
        }

    }
