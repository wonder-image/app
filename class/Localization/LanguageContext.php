<?php

    namespace Wonder\Localization;

    class LanguageContext
    {
        private static string $lang;
        private static string $langSource = "none";
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
                'link' => rtrim($link, '/').'/',
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

            self::$langSource = 'path';

            return self::setLang($code);
            
        }

        public static function setLangFromDomain(): self
        {

            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            $tld = strtolower(end($parts));

            self::$langSource = 'domain';

            return self::setLang($tld);

        }

        public static function setLangFromSubdomain(): self
        {

            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            $sub = strtolower($parts[0] ?? '');

            self::$langSource = 'subdomain';
            
            return self::setLang($sub);

        }

        public static function setLangFromQuery(): self
        {

            $code = strtolower($_GET['lang'] ?? '');

            self::$langSource = 'query';
            
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

            self::$langSource = 'header';

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

            return self::createLangUrl();
            
        }

        public static function createLangUrl(string $path = ''): string
        {

            $path = ltrim($path, '/');
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';

            $finalUrl = '';

            switch (self::$langSource) {
                case 'path':
                    $finalUrl = $scheme . '://' . $host . '/' . self::$lang . '/' . $path;
                    break;

                case 'subdomain':
                    $parts = explode('.', $host);
                    array_shift($parts); // rimuovo subdomain esistente
                    $hostWithoutSub = implode('.', $parts);
                    $finalUrl = $scheme . '://' . self::$lang . '.' . $hostWithoutSub . '/' . $path;
                    break;

                case 'domain':
                    $parts = explode('.', $host);
                    array_pop($parts); // tolgo TLD
                    $parts[] = self::$lang; // nuova lingua come TLD
                    $finalUrl = $scheme . '://' . implode('.', $parts) . '/' . $path;
                    break;

                case 'query':
                    $parsed = parse_url($uri);
                    $existingQuery = [];
                    if (!empty($parsed['query'])) {
                        parse_str($parsed['query'], $existingQuery);
                    }
                    $existingQuery['lang'] = self::$lang;
                    $newQuery = http_build_query($existingQuery);

                    $basePath = $parsed['path'] ?? '/';
                    if ($path !== '') {
                        $basePath = rtrim($basePath, '/') . '/' . $path;
                    }

                    $finalUrl = $scheme . '://' . $host . $basePath . '?' . $newQuery;
                    break;

                case 'header':
                default:
                    $finalUrl = $scheme . '://' . $host . '/' . $path;
                    break;
            }

            // aggiungi slash finale se manca e se non ci sono query o fragment
            $parsedFinal = parse_url($finalUrl);
            $hasQueryOrFragment = !empty($parsedFinal['query']) || !empty($parsedFinal['fragment']);

            if (!$hasQueryOrFragment) {
                $finalUrl = rtrim($finalUrl, '/') . '/';
            }

            return $finalUrl;
            
        }

        public static function switchLangUrl(string $url, string $lang): string
        {

            $parsed = parse_url($url);

            $scheme = $parsed['scheme'] ?? 'http';
            $host   = $parsed['host'] ?? ($_SERVER['HTTP_HOST'] ?? '');
            $path   = $parsed['path'] ?? '/';
            $query  = $parsed['query'] ?? '';

            switch (self::$langSource) {
                case 'path':
                    $segments = array_values(array_filter(explode('/', $path)));
                    if (!empty($segments)) {
                        $segments[0] = $lang; // sostituisco il primo segmento
                    } else {
                        $segments[] = $lang;
                    }
                    $path = '/' . implode('/', $segments);
                    break;

                case 'subdomain':
                    $parts = explode('.', $host);
                    if (count($parts) > 2) {
                        $parts[0] = $lang;
                    } else {
                        array_unshift($parts, $lang);
                    }
                    $host = implode('.', $parts);
                    break;

                case 'domain':
                    $parts = explode('.', $host);
                    if (count($parts) >= 2) {
                        $parts[count($parts) - 1] = $lang;
                    }
                    $host = implode('.', $parts);
                    break;

                case 'query':
                    parse_str($query, $params);
                    $params['lang'] = $lang;
                    $query = http_build_query($params);
                    break;

                case 'header':
                default:
                    return $url;
            }

            // ricostruisco URL
            $newUrl = $scheme . '://' . $host . $path;
            if (!empty($query)) {
                $newUrl .= '?' . $query;
            }
            if (!empty($parsed['fragment'])) {
                $newUrl .= '#' . $parsed['fragment'];
            }

            // aggiungo slash finale se manca e se non ci sono query o fragment
            $hasQueryOrFragment = !empty($query) || !empty($parsed['fragment']);
            if (!$hasQueryOrFragment) {
                $newUrl = rtrim($newUrl, '/') . '/';
            }

            return $newUrl;
            
        }


        public static function getPathFiles(): array
        {
            return self::$pathFile;
        }

        # Usa: https://technicalseo.com/tools/hreflang/ per i TEST 
        public static function renderHead(string $currentUrl): string
        {

            $html  = '<meta http-equiv="content-language" content="' . htmlspecialchars(self::$lang) . '">' . "\n\n";

            // x-default
            if (isset(self::$langs[self::$defaultLang])) {
                $html .= '<link rel="alternate" href="' 
                    . htmlspecialchars(self::switchLangUrl($currentUrl, self::$defaultLang)) 
                    . '" hreflang="x-default" />' . "\n";
            }

            // tutte le lingue configurate
            foreach (self::$langs as $code => $conf) {
                $html .= '<link rel="alternate" href="'
                    . htmlspecialchars(self::switchLangUrl($currentUrl, $code)) 
                    . '" hreflang="' . htmlspecialchars($code) . '" />' . "\n";
            }

            return $html;
            
        }
        
    }