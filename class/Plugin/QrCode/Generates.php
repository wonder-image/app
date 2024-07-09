<?php

    namespace Wonder\Plugin\QrCode;

    use chillerlan\QRCode\QRCode;
    use chillerlan\QRCode\QROptions;
    use chillerlan\QRCode\Data\QRMatrix;
    use chillerlan\QRCode\Output\QRGdImagePNG;
    use chillerlan\QRCode\Output\QROutputInterface;
    use chillerlan\QRCode\Common\{ Version, EccLevel };

    use Wonder\Plugin\QrCode\QRGdRounded;

    /**
     * Creazione QRCode
     * 
     * Utilizza la libreria {@link https://github.com/chillerlan/php-qrcode chillerlan}
     * 
     * @author andreamarinoni <marinoni@wonderimage.it>
     * @copyright 2024 andreamarinoni
     * @license MIT
     * 
     */

    class Generates {

        private $data, $src;

        public $version = null;
        public $eccLevel = 'L';
        public $quality = 30;
        public $quietzone = 4;
        public $color = [ 0, 0, 0 ];
        public $rounded = false;
        public $circle = false;
        public $circleRadius = 0.4;


        function __construct( $data, $src = null ) {

            $this->data = $data;
            $this->src = $src;

        }

        /**
         * 
         * Versione del QRCode
         * 
         * @param null|int $version {@link https://chillerlan.github.io/php-qrcode/classes/chillerlan-QRCode-QROptionsTrait.html#property_version}
         * @return void
         * 
         */
        public function setVersion( null|int $version ) { $this->version = $version; }

        /**
         * 
         * Livello di correzione del QRCode
         * 
         * @param string $eccLevel {@link https://chillerlan.github.io/php-qrcode/classes/chillerlan-QRCode-QROptionsTrait.html#property_eccLevel}
         * @return void
         * 
         */
        public function setEccLevel( string $eccLevel ) { $this->eccLevel = strtoupper($eccLevel); }

        /**
         * 
         * Qualità del QRCode
         * 
         * @param int $quality {@link https://chillerlan.github.io/php-qrcode/classes/chillerlan-QRCode-QROptionsTrait.html#property_quality}
         * @return void
         * 
         */
        public function setQuality( int $quality ) { $this->quality = $quality; }

        /**
         * 
         * Padding QRCode
         * 
         * @param int $quietzone {@link https://chillerlan.github.io/php-qrcode/classes/chillerlan-QRCode-QROptionsTrait.html#property_quietzoneSize}
         * @return void
         * 
         */
        public function setQuietzone( int $quietzone ) { $this->quietzone = $quietzone; }

        /**
         * 
         * Colore QRCode
         * 
         * @param array $colorRGB
         * @return void
         * 
         */
        public function setColor( array $colorRGB ) { $this->color = $colorRGB; }

        /**
         * 
         * Arrotonda QRCode
         * 
         * @param bool $rounded
         * @return void
         * 
         */
        public function setRounded( bool $rounded ) { $this->rounded = $rounded; }

        /**
         * 
         * Disegna cerchi
         * 
         * @param bool $circle
         * @param float $radius
         * @return void
         * 
         */
        public function setCircle( bool $circle, float $radius = 0.4 ) { $this->circle = $circle; $this->circleRadius = $radius; }

        public function generate() {

            $OPTIONS = [];

            if ($this->version == null) {
                $OPTIONS['version'] = Version::AUTO;
            } else {
                $OPTIONS['version'] = $this->version;
            }

            if ($this->eccLevel == 'L') {
                $OPTIONS['eccLevel'] = EccLevel::L;
            } elseif ($this->eccLevel == 'M') {
                $OPTIONS['eccLevel'] = EccLevel::M;
            }  elseif ($this->eccLevel == 'Q') {
                $OPTIONS['eccLevel'] = EccLevel::Q;
            }  elseif ($this->eccLevel == 'H') {
                $OPTIONS['eccLevel'] = EccLevel::H;
            } 

            $OPTIONS['outputType'] = QROutputInterface::CUSTOM;

            if ($this->rounded) {
                $OPTIONS['outputInterface'] = QRGdRounded::class;
            } else {
                $OPTIONS['outputInterface'] = QRGdImagePNG::class;
            }

            $OPTIONS['imageTransparent'] = false;
            $OPTIONS['scale'] = $this->quality;
            $OPTIONS['quietzoneSize'] = $this->quietzone;

            if ($this->circle) {
                $OPTIONS['drawCircularModules'] = $this->circle;
                $OPTIONS['circleRadius'] = $this->circleRadius;
            }

            $CHILLERLAN_OPTIONS = new QROptions($OPTIONS);

            $CHILLERLAN_OPTIONS->moduleValues = [
                QRMatrix::M_DARKMODULE     => $this->color,
                QRMatrix::M_DATA_DARK      => $this->color,
                QRMatrix::M_FINDER_DARK    => $this->color,
                QRMatrix::M_ALIGNMENT_DARK => $this->color,
                QRMatrix::M_TIMING_DARK    => $this->color,
                QRMatrix::M_FORMAT_DARK    => $this->color,
                QRMatrix::M_VERSION_DARK   => $this->color,
                QRMatrix::M_FINDER_DOT     => $this->color
            ];

            $QRCODE = new QRCode($CHILLERLAN_OPTIONS);

            if ($this->src == null) {
                return $QRCODE->render($this->data);
            } else {
                $QRCODE->render($this->data, $this->src);
            }

        }

    }