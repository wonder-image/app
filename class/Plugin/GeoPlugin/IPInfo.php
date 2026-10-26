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

            $this->IP = self::normalizeIp($this->IP);
            
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

            if ($this->callCurl($url) && ($code = self::countryCode($this->result['countryCode'] ?? null)) !== null) {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continentCode'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $code
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

            if ($this->callCurl($url) && ($this->result['status'] ?? null) == 'success'
                && ($code = self::countryCode($this->result['countryCode'] ?? null)) !== null) {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continentCode'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $code
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

            if ($this->callCurl($url) && ($this->result['success'] ?? false) == true
                && ($code = self::countryCode($this->result['country_code'] ?? null)) !== null) {

                $this->data = [
                    'continent' => $this->result['continent'] ?? $this->data['continent'],
                    'continent_code' => $this->result['continent_code'] ?? $this->data['continent_code'],
                    'country' => $this->result['country'] ?? $this->data['country'],
                    'country_code' => $code
                ];

                return true;

            }

            return false;

        }

        /** Codice paese ISO a due lettere, maiuscolo; null se il provider non ne dà uno valido. */
        public static function countryCode(mixed $code): ?string
        {

            $code = is_string($code) ? strtoupper(trim($code)) : '';

            return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;

        }

        /** `::ffff:203.0.113.9` (IPv4 mappato, server dual-stack) diventa `203.0.113.9`. */
        public static function normalizeIp(?string $ip): ?string
        {

            if ($ip !== null && preg_match('/^::ffff:(.+)$/i', $ip, $match) === 1
                && filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $match[1];
            }

            return $ip;

        }

        /** Solo IP pubblici: privati, locali e riservati non dicono il paese. */
        public static function isPublicIp(?string $ip): bool
        {

            $ip = self::normalizeIp($ip);

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