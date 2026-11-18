<?php

namespace Wonder\Console;

use Wonder\Console\Commands\Build;

/**
 * Snippet nginx equivalente al `.htaccess` generato da `forge build`.
 *
 * Le liste (cartelle protette, file negati, estensioni, header, cache) sono
 * quelle di `Build`: i blocchi Apache e questo snippet leggono la stessa
 * fonte. Il testo è deterministico (niente date né percorsi assoluti) perché
 * i test lo confrontano con un file atteso.
 *
 * Lo snippet va incluso nel `server { }` del vhost, PRIMA della location che
 * passa i `.php` a PHP-FPM (nginx si ferma alla prima regex che corrisponde).
 * Non contiene `root`, `fastcgi_pass`, `listen`, `ssl_*`, `gzip` né nulla che
 * appartenga all'infrastruttura.
 */
final class NginxConfig
{
    private const FRONT_CONTROLLER = '/handler/index.php';

    /**
     * @param bool $forceWww aggiunge il redirect verso `www.` (come `--force-www`)
     * @param bool $hsts     aggiunge Strict-Transport-Security (come `APP_HSTS=true`)
     */
    public static function render(bool $forceWww = false, bool $hsts = false): string
    {
        $out = [];
        $out[] = '# WONDER NGINX START';
        $out[] = '# Generato da `forge build --nginx`: non modificare a mano, viene riscritto a ogni build.';
        $out[] = '# Fonte delle regole: Wonder\\Console\\Commands\\Build (le stesse del .htaccess).';
        $out[] = '#';
        $out[] = '# Contratto con il vhost:';
        $out[] = '#  - includere questo file dentro `server { }`, PRIMA della location dei `.php`;';
        $out[] = '#  - la location dei `.php` deve passare a PHP-FPM e, se il file non esiste,';
        $out[] = '#    rimandare a '.self::FRONT_CONTROLLER.' (`try_files $uri '.self::FRONT_CONTROLLER.';`);';
        $out[] = '#  - `root`, `index`, TLS, redirect http→https, gzip e IP Cloudflare restano al vhost.';
        $out[] = '';
        $out[] = 'charset utf-8;';
        $out[] = 'fastcgi_hide_header X-Powered-By;';

        if ($forceWww) {
            $out[] = '';
            $out[] = '# Forza WWW fuori dall\'ambiente locale (--force-www)';
            $out[] = 'if ($host !~* "^(?:www\\.|localhost$|127\\.0\\.0\\.1$)") {';
            $out[] = '    return 301 https://www.$host$request_uri;';
            $out[] = '}';
        }

        // Cache-Control degli asset, calcolato una volta a livello `server`:
        // un `if` dentro una location che usa `try_files` ne annulla il
        // `try_files` (un asset mancante con ?v=N non arriverebbe al router).
        $out[] = '';
        $out[] = '# Cache degli asset: con ?v=123 è immutabile (usata nelle location sotto)';
        $out[] = 'set $wonder_cache_control "'.Build::CACHE_ASSET_CONTROL.'";';
        $out[] = 'if ($arg_v ~ "^[0-9]+$") {';
        $out[] = '    set $wonder_cache_control "'.Build::CACHE_VERSIONED_CONTROL.'";';
        $out[] = '}';

        // Cartelle mai servite. Prefisso `^~`: vince su qualunque regex, anche
        // quelle del vhost, quindi non dipende dall'ordine di inclusione.
        $out[] = '';
        $out[] = '# Cartelle mai servite via HTTP';
        foreach (Build::PROTECTED_DIRECTORIES as $directory) {
            $out[] = 'location = /'.$directory.' { return 403; }';
            $out[] = 'location ^~ /'.$directory.'/ { return 403; }';
        }

        // Upload: nessuna esecuzione, nessun `.ht*`.
        $out[] = '';
        $out[] = '# Caricamenti: mai eseguiti né serviti come script';
        $out[] = 'location ^~ /assets/upload/ {';
        $out = array_merge($out, self::indent(self::uploadBody($hsts)));
        $out[] = '}';

        // Router: queste rotte passano sempre dal front controller.
        $out[] = '';
        $out[] = '# Rotte sempre gestite dal router (esistono anche come cartelle)';
        $out[] = 'location ~* "^/(?:backend|api|oauth|\\.well-known/oauth-[a-z-]+)(?:/.*)?$" {';
        $out[] = '    rewrite ^ '.self::FRONT_CONTROLLER.' last;';
        $out[] = '}';

        $out[] = '';
        $out[] = '# File che non devono mai essere serviti';
        $out = array_merge($out, self::deniedFilesLocation());

        $out[] = '';
        $out[] = '# Archivi e backup negati ovunque tranne nei file caricati';
        $out[] = 'location ~* "^/(?!assets/upload/).*\\.(?:'.Build::ARCHIVE_EXTENSIONS.')$" { return 403; }';

        // xml-sitemaps: su Apache lo protegge il suo `data/.htaccess`.
        $sitemaps = '/vendor/wonder-image/app/vendor-static/xml-sitemaps/';
        $out[] = '';
        $out[] = '# xml-sitemaps: nginx ignora il suo data/.htaccess, queste regole ne fanno le veci';
        $out[] = 'location ~* "^'.$sitemaps.'data/(?!.*\\.(?:txt|html|gz|jpg|png|gif)$)" { return 403; }';
        $out[] = 'location ~* "^'.$sitemaps.'(?:pages/|.*\\.conf$)" { return 403; }';

        $out[] = '';
        $out[] = '# vendor/ negata, tranne gli asset statici dei pacchetti e il generatore xml-sitemaps';
        $out[] = 'location ~* "^/vendor(?:/|$)(?!'.Build::vendorAllowedPattern().')" { return 403; }';

        // Cache degli asset. `add_header` non si eredita: ogni location che ne
        // dichiara uno ripete anche gli header di sicurezza.
        $out[] = '';
        $out[] = '# Cache: pagine html sempre rivalidate';
        $out[] = 'location ~* "\\.(?:html|htm)$" {';
        $out[] = '    try_files $uri @wonder_router;';
        $out[] = '    add_header Cache-Control "'.Build::CACHE_HTML_CONTROL.'";';
        $out = array_merge($out, self::indent(self::securityHeaders($hsts)));
        $out[] = '}';

        $out[] = '';
        $out[] = '# Cache: asset statici (con ?v=123 la cache è immutabile)';
        $out[] = 'location ~* "\\.(?:'.Build::CACHE_ASSET_EXTENSIONS.')$" {';
        $out[] = '    try_files $uri @wonder_router;';
        $out = array_merge($out, self::indent(self::assetCacheHeaders($hsts)));
        $out[] = '}';

        $out[] = '';
        $out[] = '# La homepage entra sempre nel router';
        $out[] = 'location = / {';
        $out[] = '    rewrite ^ '.self::FRONT_CONTROLLER.' last;';
        $out[] = '}';

        $out[] = '';
        $out[] = '# Tutto il resto: file o cartella esistente, altrimenti il router';
        $out[] = 'location / {';
        $out[] = '    try_files $uri $uri/ @wonder_router;';
        $out = array_merge($out, self::indent(self::securityHeaders($hsts)));
        $out[] = '}';

        $out[] = '';
        $out[] = 'location @wonder_router {';
        $out[] = '    # /handler non passa dal router (404 se il file non esiste)';
        $out[] = '    if ($uri ~* "^/handler(?:/|$)") {';
        $out[] = '        return 404;';
        $out[] = '    }';
        $out[] = '    # Aggiunge lo slash finale (solo per URL senza estensione)';
        $out[] = '    if ($uri ~ "^(?!.*/$)(?!.*\\.[^./]+$)") {';
        $out[] = '        rewrite ^(.*)$ $1/ permanent;';
        $out[] = '    }';
        $out[] = '    rewrite ^ '.self::FRONT_CONTROLLER.' last;';
        $out[] = '}';
        $out[] = '# WONDER NGINX END';

        return implode("\n", $out)."\n";
    }

    /** `add_header` degli asset statici; le risposte PHP li ricevono da `SecurityHeaders`. */
    private static function securityHeaders(bool $hsts, array $override = []): array
    {
        $lines = [];
        foreach (array_merge(Build::STATIC_SECURITY_HEADERS, $override) as $name => $value) {
            $lines[] = 'add_header '.$name.' "'.$value.'";';
        }
        if ($hsts) {
            $lines[] = 'add_header Strict-Transport-Security "'.Build::HSTS_VALUE.'";';
        }

        return $lines;
    }

    private static function assetCacheHeaders(bool $hsts, array $override = []): array
    {
        return array_merge(['add_header Cache-Control $wonder_cache_control;'], self::securityHeaders($hsts, $override));
    }

    /** @return list<string> */
    private static function deniedFilesLocation(): array
    {
        $names = str_replace('.*', '[^/]*', implode('|', Build::DENIED_FILE_PATTERNS));

        return ['location ~* "(?:^|/)(?:'.$names.')$" { return 403; }'];
    }

    /** @return list<string> */
    private static function uploadBody(bool $hsts): array
    {
        $lines = [];
        $lines[] = 'location ~* "(?:\\.(?:'.Build::UPLOAD_EXECUTABLE_EXTENSIONS.')(?:\\.|$)|/\\.ht[^/]*$)" { return 403; }';
        $lines = array_merge($lines, self::deniedFilesLocation());
        $lines[] = 'location ~* "\\.svgz?$" {';
        $lines[] = '    try_files $uri @wonder_router;';
        $lines = array_merge($lines, self::indent(self::assetCacheHeaders($hsts, ['Content-Security-Policy' => 'sandbox'])));
        $lines[] = '}';
        $lines[] = 'location ~* "\\.(?:'.Build::CACHE_ASSET_EXTENSIONS.')$" {';
        $lines[] = '    try_files $uri @wonder_router;';
        $lines = array_merge($lines, self::indent(self::assetCacheHeaders($hsts)));
        $lines[] = '}';
        $lines[] = 'try_files $uri @wonder_router;';
        $lines = array_merge($lines, self::securityHeaders($hsts));

        return $lines;
    }

    /** @param list<string> $lines @return list<string> */
    private static function indent(array $lines): array
    {
        return array_map(static fn (string $line): string => $line === '' ? '' : '    '.$line, $lines);
    }
}
