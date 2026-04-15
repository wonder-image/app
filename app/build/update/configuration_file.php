<?php

    $WEBROOT = is_dir($ROOT."/public") ? $ROOT."/public" : $ROOT;
    $HTACCESS_PATH = $WEBROOT."/.htaccess";
    $ROBOTS_PATH = $WEBROOT."/robots.txt";
    $ROUTER_BLOCK = "# WONDER ROUTER START\n";
    $ROUTER_BLOCK .= "  # Router Wonder\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
    $ROUTER_BLOCK .= "  RewriteRule ^ handler/index.php [L,QSA]\n";
    $ROUTER_BLOCK .= "# WONDER ROUTER END";
    $HTACCESS_TEMPLATE = <<<'HTACCESS'
# ----------------------------------------------------------------------
# Forza HTTPS e WWW
# ----------------------------------------------------------------------
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Passa Authorization/Bearer
  RewriteCond %{HTTP:Authorization} .
  RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

  # Forza HTTPS fuori dall'ambiente locale
  RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:\d+)?$ [NC]
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

  # Forza WWW fuori dall'ambiente locale
  RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:\d+)?$ [NC]
  RewriteCond %{HTTP_HOST} !^www\. [NC]
  RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

  # Aggiunge slash finale se mancante (solo per URL senza estensione)
  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !/$
  RewriteCond %{REQUEST_URI} !\.[^./]+$
  RewriteRule ^(.*)$ /$1/ [R=301,L]

  # Router Wonder
  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ handler/index.php [L,QSA]

</IfModule>

# ----------------------------------------------------------------------
# Cache ottimizzata (immagini 24h, validazione automatica con ETag)
# ----------------------------------------------------------------------
<IfModule mod_headers.c>
  # Immagini e media: cache 24h, validabile
  <FilesMatch "\.(jpe?g|png|gif|svg|ico|pdf|mp4|webm|ogg|woff2?)$">
    Header set Cache-Control "public, max-age=86400, must-revalidate"
  </FilesMatch>

  # JS e CSS: cache 7 giorni, validabile
  <FilesMatch "\.(js|css)$">
    Header set Cache-Control "public, max-age=604800, must-revalidate"
  </FilesMatch>

  # HTML: no cache
  <FilesMatch "\.(html|htm)$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>

# Abilita intestazioni di validazione
FileETag MTime Size

# ----------------------------------------------------------------------
# Expires headers (compatibilità con browser legacy)
# ----------------------------------------------------------------------
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 1 day"
  ExpiresByType image/png "access plus 1 day"
  ExpiresByType image/gif "access plus 1 day"
  ExpiresByType image/svg+xml "access plus 1 day"
  ExpiresByType text/css "access plus 1 week"
  ExpiresByType application/javascript "access plus 1 week"
  ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# ----------------------------------------------------------------------
# Compressione (solo per testo — mai immagini o binari)
# ----------------------------------------------------------------------
<IfModule mod_deflate.c>
  # Attiva compressione solo per testi
  AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css text/javascript application/javascript application/json

  # Disattiva compressione per file binari (immagini, media, font)
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-gzip dont-vary
</IfModule>

<IfModule mod_brotli.c>
  BrotliCompressionQuality 5
  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json

  # Disattiva Brotli su file binari
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-brotli dont-vary
</IfModule>

# ----------------------------------------------------------------------
# Gestione automatica degli errori con redirect
# ----------------------------------------------------------------------
<IfModule mod_rewrite.c>
  # Intercetta solo se REDIRECT_STATUS è compreso tra 400 e 599
  RewriteCond %{ENV:REDIRECT_STATUS} >=400
  RewriteCond %{ENV:REDIRECT_STATUS} <600
  RewriteCond %{QUERY_STRING} !errCode=
  RewriteRule ^.*$ /?errCode=%{ENV:REDIRECT_STATUS} [R=302,L]
</IfModule>

# ----------------------------------------------------------------------
# Sicurezza e policy moderne
# ----------------------------------------------------------------------
<IfModule mod_headers.c>
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
  Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# ----------------------------------------------------------------------
# Impostazioni finali
# ----------------------------------------------------------------------
Options -Indexes
AddDefaultCharset UTF-8
DefaultLanguage it
HTACCESS;

    if (file_exists($HTACCESS_PATH)) {

        $htaccessContent = file_get_contents($HTACCESS_PATH);

        if (is_string($htaccessContent) && $htaccessContent !== '') {
            $updatedContent = preg_replace('/^Redirect 301 \/update\/ \/\?updateApp=true\r?\n/m', '', $htaccessContent);
            $updatedContent = preg_replace('/\n?# WONDER ROUTER START.*?# WONDER ROUTER END\r?\n?/s', "\n", (string) $updatedContent);

            if (is_string($updatedContent)) {
                if (str_contains($updatedContent, '# Aggiunge slash finale se mancante')) {
                    $updatedContent = str_replace(
                        "  # Router Wonder\n  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]\n  RewriteCond %{REQUEST_FILENAME} !-f\n  RewriteCond %{REQUEST_FILENAME} !-d\n  RewriteRule ^ handler/index.php [L,QSA]\n",
                        $ROUTER_BLOCK."\n",
                        $updatedContent
                    );
                } else {
                    $updatedContent = rtrim($updatedContent)."\n\n".$ROUTER_BLOCK."\n";
                }
            }

            if (is_string($updatedContent) && $updatedContent !== $htaccessContent) {
                file_put_contents($HTACCESS_PATH, $updatedContent);
            }
        }

    }

    if (!file_exists($HTACCESS_PATH)) {
        
        $FILE = fopen($HTACCESS_PATH, "w");
        fwrite($FILE, $HTACCESS_TEMPLATE.PHP_EOL);
        fclose($FILE);

    } 

    if (!file_exists($ROBOTS_PATH)) {

        $FILE = fopen($ROBOTS_PATH, "w");
        
        $FILE_CONTENT = "User-agent: *\n";
        $FILE_CONTENT .= "Disallow: /backend/\n";
        $FILE_CONTENT .= "\n";
        $FILE_CONTENT .= "Sitemap: https://www.$PAGE->domain/shared/sitemap/sitemap.xml";

        fwrite($FILE, $FILE_CONTENT);
        fclose($FILE);
        
    }
