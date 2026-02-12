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
            $https = $_SERVER['HTTPS'] ?? '';
            return (!empty($https) && $https !== 'off') ? 'https' : 'http';
        }

        private function currentHost(): string
        {
            // Determina l'host attuale.
            return $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        }
    }
