<?php

    namespace Wonder\Localization;

    use Wonder\Localization\LanguageContext;

    class LanguageRedirector
    {
        private array $langs;
        private string $defaultLang;

        public function __construct()
        {
            $this->langs = LanguageContext::getLangs();
            $this->defaultLang = LanguageContext::getDefaultLang();
        }

        public function isBot(): bool
        {

            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            return str_contains($ua, 'googlebot')
                || str_contains($ua, 'bingbot')
                || str_contains($ua, 'yandex')
                || str_contains($ua, 'duckduckbot')
                || str_contains($ua, 'baiduspider');

        }

        /**
         * Redirect alla home della lingua del visitatore.
         *
         * Risoluzione: paese (di default la cache di sessione popolata da
         * `config/app/session.php`) → header `Accept-Language` → lingua
         * default. I bot restano sulla lingua default per stabilità SEO.
         *
         * Lo status è 302 perché la destinazione varia per visitatore: un
         * 301 verrebbe cachato dal browser e ignorerebbe cambi di lingua.
         */
        public function redirectByCountry(?string $country = null, int $status = 302): never
        {

            $country ??= $_SESSION['system_cache']['country'] ?? null;

            $lang = $this->defaultLang;

            if (!$this->isBot()) {
                $lang = $this->langByCountry($country)
                    ?? $this->langByHeader()
                    ?? $this->defaultLang;
            }

            $link = $this->langs[$lang]['link'] ?? '/'.$lang.'/';

            header('Vary: Accept-Language');
            header("Location: {$link}", true, $status);
            exit;

        }

        private function langByCountry(?string $country): ?string
        {

            if (empty($country)) {
                return null;
            }

            foreach ($this->langs as $code => $conf) {
                if (in_array($country, $conf['countries'], true)) {
                    return $code;
                }
            }

            return null;

        }

        private function langByHeader(): ?string
        {

            $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

            if ($header === '') {
                return null;
            }

            // Esempio header: "it-IT,it;q=0.9,en;q=0.8" — le voci sono già
            // in ordine di preferenza, basta la prima lingua registrata.
            foreach (explode(',', $header) as $entry) {
                $code = strtolower(substr(trim($entry), 0, 2));
                if (isset($this->langs[$code])) {
                    return $code;
                }
            }

            return null;

        }

    }
