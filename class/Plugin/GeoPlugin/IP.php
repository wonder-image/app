<?php

    namespace Wonder\Plugin\GeoPlugin;

    class IPInfo {

        public $IP;
        private $GEO_PLUGIN;

        public function __construct($ip = null) {
            
            $this->IP = ($ip == null) ? $_SERVER['REMOTE_ADDR'] : $ip;
            $this->GeoPlugin();

        }

        private function GeoPlugin() {
            $this->GEO_PLUGIN = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip='.$this->IP));
        }

        public function Continent() { return $this->GEO_PLUGIN['geoplugin_continentCode']; }
        public function ContinentName() { return $this->GEO_PLUGIN['geoplugin_continentName']; }
        
        public function Country() { return $this->GEO_PLUGIN['geoplugin_countryCode']; }
        public function CountryName() { return $this->GEO_PLUGIN['geoplugin_countryName']; }
        

    }