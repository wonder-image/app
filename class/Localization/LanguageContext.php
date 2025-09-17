<?php

    namespace Wonder\Localization;

    class LanguageContext
    {
        private static string $lang;
        private static string $defaultLang = 'it';
        private static array $langs = [];
        private static array $pathFile = [];

        public static function addLangPath(string $pathLangFile): self
        {

            self::$lang = array_push(self::$pathFile, rtrim($pathLangFile, '/'));

            return new self();

        }

        public static function defaultLang(string $defaultLang): self
        {

            self::$defaultLang = $defaultLang;
            self::$lang = $defaultLang;

            return new self();

        }
        

        public static function addLanguage(string $code, string $name, string $link, string $flag, array $countries = []): self
        {

            self::$langs[$code] = [
                'name' => $name,
                'link' => rtrim($link, '/'),
                'flag' => $flag,
                'countries' => $countries,
            ];

            return new self();

        }

        public static function setLang(string $code): self
        {

            self::$lang = (!isset(self::$langs[$code])) ? self::$defaultLang : $code;

            header('Content-Language: ' . self::$lang);

            return new self();

        }

        public static function setLangFromPath(): self
        {

            $parsedUri = parse_url($_SERVER["REQUEST_URI"]);
            $pathArray = array_values(array_filter(explode('/', $parsedUri["path"] ?? '')));
            $code = isset($pathArray[0]) ? strtolower($pathArray[0]) : self::$defaultLang;

            return self::setLang($code);
            
        }

        public static function setLangFromDomain(): self
        {

            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            $tld = strtolower(end($parts));

            return self::setLang($tld);

        }

        public static function setLangFromSubdomain(): self
        {

            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            $sub = strtolower($parts[0] ?? '');
            
            return self::setLang($sub);

        }

        public static function setLangFromQuery(): self
        {

            $code = strtolower($_GET['lang'] ?? '');
            
            return self::setLang($code);

        }

        public static function setLangFromHeader(): self
        {

            if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = self::$defaultLang;
            }

            // Esempio header: "it-IT,it;q=0.9,en;q=0.8"
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if (!empty($langs)) {
                // prendo il codice della prima lingua (prima di "-")
                $lang = substr($langs[0], 0, 2);
            }

            return self::setLang($lang);

        }

        public static function getLang(): string
        {
            return self::$lang ?? self::$defaultLang;
        }

        public static function getDefaultLang(): string
        {
            return self::$defaultLang;
        }

        public static function getLangs(): array
        {
            return self::$langs;
        }

        public static function getSitePath(): string
        {

            if (isset(self::$langs[self::$lang]['link'])) {
                return rtrim(self::$langs[self::$lang]['link'], '/');
            }

            return rtrim(self::$langs[self::$defaultLang]['link'] ?? '/', '/');

        }

        public static function getPathFiles(): array
        {
            return self::$pathFile;
        }

        # Usa: https://technicalseo.com/tools/hreflang/ per i TEST 
        public static function renderHead(string $currentUrl): string
        {

            $baseSite = self::$langs[self::$lang]['link'];

            $html  = '<meta http-equiv="content-language" content="' . htmlspecialchars(self::$lang) . '">' . "\n\n";

            // x-default
            if (isset(self::$langs[self::$defaultLang])) {
                $html .= '<link rel="alternate" href="' 
                    . htmlspecialchars(str_replace($baseSite, self::$langs[self::$defaultLang]['link'], $currentUrl)) 
                    . '" hreflang="x-default" />' . "\n";
            }

            // tutte le lingue configurate
            foreach (self::$langs as $code => $conf) {
                $html .= '<link rel="alternate" href="'
                    . htmlspecialchars(str_replace($baseSite, $conf['link'], $currentUrl))
                    . '" hreflang="' . htmlspecialchars($code) . '" />' . "\n";
            }

            return $html;
            
        }
        
    }