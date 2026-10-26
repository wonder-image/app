<?php

    namespace Wonder\Plugin\GeoPlugin;

    use Wonder\Api\Call;
    use Wonder\App\Credentials;

    class IPInfo {

        public $IP;
        private $result;
        public array $data = [
            'continent' => 'Europe',
            'continent_code' => 'EU',
            'country' => 'Italy',
            'country_code' => 'IT'
        ];

        public function __construct($ip = null) {
            
            if ($ip === null) {
                
                // Stessa regola di throttle e auth_log: X-Forwarded-For solo con APP_TRUST_PROXY.
                $ip = \Wonder\Http\ClientIp::resolve($_SERVER);
                $this->IP = $ip === '' ? null : $ip;

            } else {

                $this->IP = $ip;

            }
            
            $this->searchData();

        }

        private function callCurl($url): bool
        {

            $call = (new Call($url))
                    ->method('GET')
                    ->timeout(3)
                    ->connectTimeout(1)
                    ->call();

            if (!$call['success']) { return false; }

            $result = json_decode($call['result'], true);
            if ($result === null) { return false; }

            $this->result = $result;
            return true;

        }

        private function ipInfo(): bool
        {

            $url = 'https://api.ipinfo.io/lite/';
            $url .= $this->IP;
            $url .= '?token='.Credentials::api()->ipinfo_api_key;

            if ($this->callCurl($url)) {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continentCode'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $this->result['countryCode'] ?? $this->data['country_code']
                ];

                return true;

            }

            return false;

        }

        # https://ip-api.com
        private function ipApi(): bool 
        {

            $url = 'http://ip-api.com/json/';
            $url .= $this->IP;
            $url .= '?fields=status,message,continent,continentCode,country,countryCode,region,regionName,city,zip,lat,lon,timezone,currency,isp,org,as,query';

            if ($this->callCurl($url) && $this->result['status'] == 'success') {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continentCode'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $this->result['countryCode'] ?? $this->data['country_code']
                ];

                return true;

            }

            return false;

        }

        # https://ipwhois.io
        private function ipWhois(): bool 
        {

            $url = 'http://ipwho.is/';
            $url .= $this->IP;

            if ($this->callCurl($url) && $this->result['success'] == true) {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continent_code'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $this->result['country_code'] ?? $this->data['country_code']
                ];

                return true;

            }

            return false;

        }

        /** Solo IP pubblici: privati, locali e riservati non dicono il paese. */
        public static function isPublicIp(?string $ip): bool
        {

            return $ip !== null && filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

        }

        private function searchData() {

            if (self::isPublicIp($this->IP)) {

                if ($this->ipInfo()) {
                    # Risultato trovato da ipinfo.io
                } else if ($this->ipApi()) {
                    # Risultato trovato da ip-api.com
                } else if ($this->ipWhois()) {
                    # Risultato trovato da ipwhois.is
                }

            }

        }

        public function Continent() { return $this->data['continent_code']; }
        public function ContinentName() { return $this->data['continent']; }

        public function Country() { return $this->data['country_code']; }
        public function CountryName() { return $this->data['country']; }
        
    }