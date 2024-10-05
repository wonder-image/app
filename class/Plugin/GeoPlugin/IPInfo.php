<?php

    namespace Wonder\Plugin\GeoPlugin;

    class IPInfo {

        public $IP;
        private $GEO_PLUGIN;

        public function __construct($ip = null) {
            
            if (is_null($ip)) {
                
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $this->IP = $_SERVER['REMOTE_ADDR'];
                } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $this->IP = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $this->IP = $_SERVER['HTTP_CLIENT_IP'];
                } else {
                    $this->IP = null;
                }

            } else {

                $this->IP = $ip;

            }
            
            $this->GeoPlugin();

        }

        private function GeoPlugin() {
            $this->GEO_PLUGIN = is_null($this->IP) ? [] : unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip='.$this->IP));
        }

        public function Continent() { return $this->GEO_PLUGIN['geoplugin_continentCode'] ?? ''; }
        public function ContinentName() { return $this->GEO_PLUGIN['geoplugin_continentName'] ?? ''; }
        
        public function Country() { return $this->GEO_PLUGIN['geoplugin_countryCode'] ?? ''; }
        public function CountryName() { return $this->GEO_PLUGIN['geoplugin_countryName'] ?? ''; }
        
    }