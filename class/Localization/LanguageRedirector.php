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

        public function redirectByCountry(?string $country): void
        {

            $lang = $this->defaultLang;

            if (!$this->isBot() && $country) {
                foreach ($this->langs as $code => $conf) {
                    if (in_array($country, $conf['countries'], true)) {
                        $lang = $code;
                        break;
                    }
                }
            }

            header("Location: {$this->langs[$lang]['link']}", true, 301);
            exit;

        }

    }