<?php

    namespace Wonder\Http;

    class UrlParser
    {
        private string $rawUrl;
        private string $fullUrl;
        private array $parts;
        private array $rawParts;

        public function __construct(?string $url = null)
        {
            $this->rawUrl = $url ?? '';
            // Normalizza l'URL per avere sempre schema + host.
            $this->fullUrl = $this->normalizeUrl($url);
            // Esegue il parsing una sola volta.
            $this->parts = parse_url($this->fullUrl) ?: [];
            $this->rawParts = parse_url($this->rawUrl) ?: [];
        }

        public function getUrl(): string
        {
            // Ritorna l'URL completo normalizzato.
            return $this->fullUrl;
        }

        public function getDomain(): ?string
        {
            // Ritorna il dominio senza "www".
            $host = $this->parts['host'] ?? null;
            if (empty($host)) { return null; }
            return preg_replace('/^www\./i', '', $host);
        }

        public function getScheme(bool $raw = false): ?string
        {
            $parts = $raw ? $this->rawParts : $this->parts;
            return $parts['scheme'] ?? null;
        }

        public function getHost(bool $raw = false): ?string
        {
            $parts = $raw ? $this->rawParts : $this->parts;
            return $parts['host'] ?? null;
        }

        public function getPort(bool $raw = false): ?int
        {
            $parts = $raw ? $this->rawParts : $this->parts;
            return isset($parts['port']) ? (int) $parts['port'] : null;
        }

        public function getQuery(bool $raw = false): ?string
        {
            // Ritorna la query string (es. a=1&b=2).
            $parts = $raw ? $this->rawParts : $this->parts;
            return $parts['query'] ?? null;
        }

        public function getPath(bool $raw = false): ?string
        {
            // Ritorna il path dell'URL.
            $parts = $raw ? $this->rawParts : $this->parts;
            return $parts['path'] ?? null;
        }

        public function getFragment(bool $raw = false): ?string
        {
            $parts = $raw ? $this->rawParts : $this->parts;
            return $parts['fragment'] ?? null;
        }

        public function getDir(int $index): ?string
        {
            // Ritorna la dir in posizione 1-based.
            $path = $this->parts['path'] ?? '';
            $segments = array_values(array_filter(explode('/', $path)));
            $pos = $index - 1;
            return $segments[$pos] ?? null;
        }

        public function getParam(string $name): ?string
        {
            // Ritorna il valore di un parametro di query.
            $query = $this->parts['query'] ?? '';
            if ($query === '') { return null; }
            parse_str($query, $params);
            return $params[$name] ?? null;
        }
        public function getBaseUrl(bool $raw = false): ?string
        {

            $scheme = $this->getScheme($raw);
            $host = $this->getHost($raw);
            $port = $this->getPort($raw);

            if (empty($host)) {
                return null;
            }

            $baseUrl = '';

            if (!empty($scheme)) {
                $baseUrl .= $scheme . '://';
            } else {
                $baseUrl .= '//';
            }

            $baseUrl .= $host;

            if (!empty($port)) {
                $baseUrl .= ':' . $port;
            }

            return $baseUrl;
            
        }

        public function addParameter(string $name, mixed $value): string
        {
            // Aggiunge il parametro solo se non esiste già.
            return $this->applyParameterOperation($name, $value, 'add');
        }

        public function addOrReplaceParameter(string $name, mixed $value): string
        {
            // Aggiunge il parametro o lo sostituisce se già presente.
            return $this->applyParameterOperation($name, $value, 'add_or_replace');
        }

        public function replaceParameter(string $name, mixed $value): string
        {
            // Sostituisce il parametro solo se è già presente.
            return $this->applyParameterOperation($name, $value, 'replace');
        }

        public function getFile(): ?string
        {
            // Ritorna il nome file con estensione.
            $path = $this->parts['path'] ?? '';
            if ($path === '' || substr($path, -1) === '/') { return null; }
            return basename($path);
        }

        public function getFileName(): ?string
        {
            // Ritorna il nome file senza estensione.
            $file = $this->getFile();
            if ($file === null) { return null; }
            $info = pathinfo($file);
            return $info['filename'] ?? null;
        }

        public function isAbsolute(): bool
        {
            return (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $this->rawUrl)
                || str_starts_with($this->rawUrl, '//');
        }

        public static function requestUri(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            return (string) ($server['REQUEST_URI'] ?? '/');
        }

        public static function requestIp(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            $forwardedFor = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');

            if ($forwardedFor !== '') {
                $firstIp = trim(explode(',', $forwardedFor)[0]);
                if ($firstIp !== '') {
                    return $firstIp;
                }
            }

            return (string) ($server['REMOTE_ADDR'] ?? '');
        }

        public static function requestHost(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            $host = trim((string) ($server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? 'localhost')));
            return $host !== '' ? $host : 'localhost';
        }

        public static function isHttpsRequest(?array $server = null): bool
        {
            $server = $server ?? $_SERVER;

            $https = strtolower((string) ($server['HTTPS'] ?? ''));
            if (in_array($https, [ 'on', '1', 'https' ], true)) {
                return true;
            }

            $forwardedProto = self::firstForwardedHeaderValue((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($forwardedProto === 'https') {
                return true;
            }

            $forwardedScheme = self::firstForwardedHeaderValue((string) ($server['HTTP_X_FORWARDED_SCHEME'] ?? ''));
            if ($forwardedScheme === 'https') {
                return true;
            }

            $forwardedSsl = strtolower(trim((string) ($server['HTTP_X_FORWARDED_SSL'] ?? '')));
            if (in_array($forwardedSsl, [ 'on', '1' ], true)) {
                return true;
            }

            $requestScheme = strtolower(trim((string) ($server['REQUEST_SCHEME'] ?? '')));
            if ($requestScheme === 'https') {
                return true;
            }

            $serverPort = (int) ($server['SERVER_PORT'] ?? 0);
            return $serverPort === 443;
        }

        public static function requestScheme(?array $server = null): string
        {
            return self::isHttpsRequest($server) ? 'https' : 'http';
        }

        public static function normalizeDomain(string $domain): string
        {
            $domain = trim(strtolower($domain));

            if ($domain === '') {
                return '';
            }

            if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
                $host = parse_url($domain, PHP_URL_HOST);
                $domain = is_string($host) ? $host : '';
            } else {
                $domain = preg_replace('#/.*$#', '', $domain) ?? '';
            }

            $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
            $domain = rtrim($domain, '.');

            return trim($domain);
        }

        public static function normalizeDomainPattern(string $pattern): string
        {
            $pattern = trim(strtolower($pattern));

            if ($pattern === '') {
                return '';
            }

            $hasWildcard = str_starts_with($pattern, '*.');
            if ($hasWildcard) {
                $pattern = substr($pattern, 2);
            }

            $pattern = self::normalizeDomain($pattern);

            if ($pattern === '') {
                return '';
            }

            return $hasWildcard ? '*.'.$pattern : $pattern;
        }

        public static function matchesDomain(
            string $requestDomain,
            string $allowedDomainPattern,
            bool $ignoreWww = true,
            bool $includeBaseForWildcard = true
        ): bool {
            $requestDomain = self::normalizeDomain($requestDomain);
            $pattern = self::normalizeDomainPattern($allowedDomainPattern);

            if ($requestDomain === '' || $pattern === '') {
                return false;
            }

            $requestComparable = $ignoreWww ? self::removeWwwPrefix($requestDomain) : $requestDomain;

            if (str_starts_with($pattern, '*.')) {
                $base = substr($pattern, 2);
                $baseComparable = $ignoreWww ? self::removeWwwPrefix($base) : $base;

                if ($includeBaseForWildcard && $requestComparable === $baseComparable) {
                    return true;
                }

                return str_ends_with($requestComparable, '.'.$baseComparable);
            }

            $patternComparable = $ignoreWww ? self::removeWwwPrefix($pattern) : $pattern;
            return $requestComparable === $patternComparable;
        }

        public static function matchesAnyDomain(
            string $requestDomain,
            array $allowedDomains,
            bool $ignoreWww = true,
            bool $includeBaseForWildcard = true
        ): bool {
            foreach ($allowedDomains as $allowedDomain) {
                if (!is_string($allowedDomain) && !is_numeric($allowedDomain)) {
                    continue;
                }

                if (self::matchesDomain($requestDomain, (string) $allowedDomain, $ignoreWww, $includeBaseForWildcard)) {
                    return true;
                }
            }

            return false;
        }

        public static function parseQueryString(string $queryString, array $fallback = []): array
        {
            if ($queryString === '') {
                return $fallback;
            }

            $parameters = [];

            foreach (explode('&', $queryString) as $pair) {
                if ($pair === '') {
                    continue;
                }

                $parts = explode('=', $pair, 2);
                $rawKey = urldecode($parts[0] ?? '');
                $rawValue = urldecode($parts[1] ?? '');

                if ($rawKey === '') {
                    continue;
                }

                if (preg_match('/\[[^\]]+\]/', $rawKey) && !str_ends_with($rawKey, '[]')) {
                    continue;
                }

                if (str_ends_with($rawKey, '[]')) {
                    $key = substr($rawKey, 0, -2);

                    if (!array_key_exists($key, $parameters) || !is_array($parameters[$key])) {
                        $parameters[$key] = [];
                    }

                    $parameters[$key][] = $rawValue;
                    continue;
                }

                if (!array_key_exists($rawKey, $parameters)) {
                    $parameters[$rawKey] = $rawValue;
                } else {
                    if (!is_array($parameters[$rawKey])) {
                        $parameters[$rawKey] = [ $parameters[$rawKey] ];
                    }
                    $parameters[$rawKey][] = $rawValue;
                }
            }

            foreach ($fallback as $key => $value) {
                if (!array_key_exists($key, $parameters)) {
                    $parameters[$key] = $value;
                }
            }

            return $parameters;
        }

        public static function requestQueryParameters(?array $server = null, ?array $fallback = null): array
        {
            $server = $server ?? $_SERVER;
            $fallback = $fallback ?? (is_array($_GET ?? null) ? $_GET : []);
            $queryString = (string) ($server['QUERY_STRING'] ?? '');

            return self::parseQueryString($queryString, is_array($fallback) ? $fallback : []);
        }

        private function applyParameterOperation(string $name, mixed $value, string $mode): string
        {
            $name = trim($name);

            if ($name === '') {
                return $this->rawUrl !== '' ? $this->rawUrl : $this->fullUrl;
            }

            $parts = !empty($this->rawParts) ? $this->rawParts : $this->parts;
            $query = self::parseQueryString((string) ($parts['query'] ?? ''));
            $exists = array_key_exists($name, $query);
            $normalizedValue = self::normalizeQueryValue($value);

            if ($mode === 'add') {
                if (!$exists) {
                    $query[$name] = $normalizedValue;
                }
            } elseif ($mode === 'replace') {
                if ($exists) {
                    $query[$name] = $normalizedValue;
                }
            } else {
                $query[$name] = $normalizedValue;
            }

            return self::buildUrlWithQuery($parts, $query);
        }

        private static function normalizeQueryValue(mixed $value): mixed
        {
            if (is_array($value)) {
                $normalized = [];
                foreach ($value as $key => $item) {
                    $normalized[$key] = self::normalizeQueryValue($item);
                }
                return $normalized;
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if ($value === null) {
                return '';
            }

            return is_scalar($value) ? (string) $value : '';
        }

        private static function buildUrlWithQuery(array $parts, array $query): string
        {
            $url = '';
            $host = (string) ($parts['host'] ?? '');
            $scheme = (string) ($parts['scheme'] ?? '');
            $user = (string) ($parts['user'] ?? '');
            $pass = (string) ($parts['pass'] ?? '');
            $path = (string) ($parts['path'] ?? '');
            $fragment = (string) ($parts['fragment'] ?? '');
            $port = isset($parts['port']) ? (int) $parts['port'] : null;

            if ($host !== '') {
                if ($scheme !== '') {
                    $url .= $scheme.'://';
                } else {
                    $url .= '//';
                }

                if ($user !== '') {
                    $url .= $user;
                    if ($pass !== '') {
                        $url .= ':'.$pass;
                    }
                    $url .= '@';
                }

                $url .= $host;

                if ($port !== null) {
                    $url .= ':'.$port;
                }
            }

            $url .= $path;

            $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($queryString !== '') {
                $url .= '?'.$queryString;
            }

            if ($fragment !== '') {
                $url .= '#'.$fragment;
            }

            return $url;
        }

        private function normalizeUrl(?string $url): string
        {
            // Se non passato, usa l'URL corrente.
            if (empty($url)) {
                return $this->currentUrl();
            }

            // Se ha gia lo schema, e' completo.
            if (parse_url($url, PHP_URL_SCHEME)) {
                return $url;
            }

            // Gestisce URL schema-relative (//example.com/..).
            if (strpos($url, '//') === 0) {
                return $this->currentScheme() . ':' . $url;
            }

            // Altrimenti lo considera un path relativo/assoluto.
            $base = $this->currentBase();
            if ($url[0] !== '/') { $url = '/' . $url; }
            return $base . $url;
        }

        private function currentUrl(): string
        {
            // Costruisce l'URL completo dalla richiesta corrente.
            $request = $_SERVER['REQUEST_URI'] ?? '/';
            return $this->currentBase() . $request;
        }

        private function currentBase(): string
        {
            // Costruisce schema + host corrente.
            return $this->currentScheme() . '://' . $this->currentHost();
        }

        private function currentScheme(): string
        {
            // Determina lo schema attuale.
            return self::requestScheme($_SERVER);
        }

        private function currentHost(): string
        {
            // Determina l'host attuale.
            return self::requestHost($_SERVER);
        }

        private static function firstForwardedHeaderValue(string $value): string
        {
            if ($value === '') {
                return '';
            }

            return strtolower(trim(explode(',', $value)[0]));
        }

        private static function removeWwwPrefix(string $domain): string
        {
            return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
        }
    }
