<?php

    if (!file_exists($ROOT."/.htaccess")) {
        
        $FILE = fopen($ROOT."/.htaccess", "w");
        
        $FILE_CONTENT  = "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Forza HTTPS e WWW\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_rewrite.c>\n";
        $FILE_CONTENT .= "  RewriteEngine On\n\n";
        $FILE_CONTENT .= "  # Reindirizza sempre su HTTPS e WWW\n";
        $FILE_CONTENT .= "  RewriteCond %{HTTPS} off [OR]\n";
        $FILE_CONTENT .= "  RewriteCond %{HTTP_HOST} !^www\\. [NC]\n";
        $FILE_CONTENT .= "  RewriteRule ^(.*)$ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n";
        $FILE_CONTENT .= "  # Aggiunge slash finale se mancante (solo per URL senza estensione)\n";
        $FILE_CONTENT .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
        $FILE_CONTENT .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
        $FILE_CONTENT .= "  RewriteCond %{REQUEST_URI} !/$\n";
        $FILE_CONTENT .= "  RewriteCond %{REQUEST_URI} !\\.[^./]+$\n";
        $FILE_CONTENT .= "  RewriteRule ^(.*)$ /$1/ [R=301,L]\n\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Cache ottimizzata (immagini 24h, validazione automatica con ETag)\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_headers.c>\n";
        $FILE_CONTENT .= "  # Immagini e media: cache 24h, validabile\n";
        $FILE_CONTENT .= "  <FilesMatch \"\\.(jpe?g|png|gif|svg|ico|pdf|mp4|webm|ogg|woff2?)$\">\n";
        $FILE_CONTENT .= "    Header set Cache-Control \"public, max-age=86400, must-revalidate\"\n";
        $FILE_CONTENT .= "  </FilesMatch>\n\n";
        $FILE_CONTENT .= "  # JS e CSS: cache 7 giorni, validabile\n";
        $FILE_CONTENT .= "  <FilesMatch \"\\.(js|css)$\">\n";
        $FILE_CONTENT .= "    Header set Cache-Control \"public, max-age=604800, must-revalidate\"\n";
        $FILE_CONTENT .= "  </FilesMatch>\n\n";
        $FILE_CONTENT .= "  # HTML: no cache\n";
        $FILE_CONTENT .= "  <FilesMatch \"\\.(html|htm)$\">\n";
        $FILE_CONTENT .= "    Header set Cache-Control \"no-cache, must-revalidate\"\n";
        $FILE_CONTENT .= "  </FilesMatch>\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# Abilita intestazioni di validazione\n";
        $FILE_CONTENT .= "FileETag MTime Size\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Expires headers (compatibilità con browser legacy)\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_expires.c>\n";
        $FILE_CONTENT .= "  ExpiresActive On\n";
        $FILE_CONTENT .= "  ExpiresByType image/jpeg \"access plus 1 day\"\n";
        $FILE_CONTENT .= "  ExpiresByType image/png \"access plus 1 day\"\n";
        $FILE_CONTENT .= "  ExpiresByType image/gif \"access plus 1 day\"\n";
        $FILE_CONTENT .= "  ExpiresByType image/svg+xml \"access plus 1 day\"\n";
        $FILE_CONTENT .= "  ExpiresByType text/css \"access plus 1 week\"\n";
        $FILE_CONTENT .= "  ExpiresByType application/javascript \"access plus 1 week\"\n";
        $FILE_CONTENT .= "  ExpiresByType text/html \"access plus 0 seconds\"\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Compressione (solo per testo — mai immagini o binari)\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_deflate.c>\n";
        $FILE_CONTENT .= "  # Attiva compressione solo per testi\n";
        $FILE_CONTENT .= "  AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css text/javascript application/javascript application/json\n\n";
        $FILE_CONTENT .= "  # Disattiva compressione per file binari (immagini, media, font)\n";
        $FILE_CONTENT .= "  SetEnvIfNoCase Request_URI \"\\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$\" no-gzip dont-vary\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "<IfModule mod_brotli.c>\n";
        $FILE_CONTENT .= "  BrotliCompressionQuality 5\n";
        $FILE_CONTENT .= "  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json\n\n";
        $FILE_CONTENT .= "  # Disattiva Brotli su file binari\n";
        $FILE_CONTENT .= "  SetEnvIfNoCase Request_URI \"\\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$\" no-brotli dont-vary\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Gestione automatica degli errori con redirect\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_rewrite.c>\n";
        $FILE_CONTENT .= "  # Intercetta solo se REDIRECT_STATUS è compreso tra 400 e 599\n";
        $FILE_CONTENT .= "  RewriteCond %{ENV:REDIRECT_STATUS} >=400\n";
        $FILE_CONTENT .= "  RewriteCond %{ENV:REDIRECT_STATUS} <600\n";
        $FILE_CONTENT .= "  RewriteCond %{QUERY_STRING} !errCode=\n";
        $FILE_CONTENT .= "  RewriteRule ^.*$ /?errCode=%{ENV:REDIRECT_STATUS} [R=302,L]\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Redirect statici\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "Redirect 301 /update/ /?updateApp=true\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Sicurezza e policy moderne\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "<IfModule mod_headers.c>\n";
        $FILE_CONTENT .= "  Header always set X-Frame-Options \"SAMEORIGIN\"\n";
        $FILE_CONTENT .= "  Header always set X-Content-Type-Options \"nosniff\"\n";
        $FILE_CONTENT .= "  Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        $FILE_CONTENT .= "  Header always set Permissions-Policy \"camera=(), microphone=(), geolocation=()\"\n";
        $FILE_CONTENT .= "  Header always set X-XSS-Protection \"1; mode=block\"\n";
        $FILE_CONTENT .= "</IfModule>\n\n";

        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "# Impostazioni finali\n";
        $FILE_CONTENT .= "# ----------------------------------------------------------------------\n";
        $FILE_CONTENT .= "Options -Indexes\n";
        $FILE_CONTENT .= "AddDefaultCharset UTF-8\n";
        $FILE_CONTENT .= "DefaultLanguage it\n";

        fwrite($FILE, $FILE_CONTENT);
        fclose($FILE);

    } 

    if (!file_exists($ROOT."/robots.txt")) {

        $FILE = fopen($ROOT."/robots.txt", "w");
        
        $FILE_CONTENT = "User-agent: *\n";
        $FILE_CONTENT .= "Disallow: /backend/\n";
        $FILE_CONTENT .= "\n";
        $FILE_CONTENT .= "Sitemap: https://www.$PAGE->domain/shared/sitemap/sitemap.xml";

        fwrite($FILE, $FILE_CONTENT);
        fclose($FILE);
        
    }