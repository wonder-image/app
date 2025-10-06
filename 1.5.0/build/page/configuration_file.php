<?php

    if (!file_exists($ROOT."/.htaccess")) {
        
        $FILE = fopen($ROOT."/.htaccess", "w");

        $FILE_TXT = "## Forza HTTPS e WWW\n";
        $FILE_TXT .= "RewriteCond %{HTTPS} off [OR]\n";
        $FILE_TXT .= "RewriteCond %{HTTP_HOST} !^www\. [NC]\n";
        $FILE_TXT .= "RewriteRule ^(.*)$ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Aggiunge lo slash finale a tutte le URL se manca";
        $FILE_TXT .= "RewriteCond %{REQUEST_FILENAME} !-f";
        $FILE_TXT .= "RewriteCond %{REQUEST_FILENAME} !-d";
        $FILE_TXT .= "RewriteCond %{REQUEST_URI} !/$";
        $FILE_TXT .= "RewriteCond %{REQUEST_URI} !\.[^./]+$ # esclude file con estensione";
        $FILE_TXT .= "RewriteRule ^(.*)$ /$1/ [R=301,L]";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Abilita il caching del browser\n";
        $FILE_TXT .= "<IfModule mod_headers.c>\n";
        $FILE_TXT .= "  <FilesMatch \"\\.(jpe?g|png|webp|gif|swf|flv|pdf|mp4|webm|gz)$\">\n";
        $FILE_TXT .= "      Header set Cache-Control \"max-age=86400, public\"\n";
        $FILE_TXT .= "  </FilesMatch>\n";
        $FILE_TXT .= "  <FilesMatch \"\\.(js|css|html|htm|ico)$\">\n";
        $FILE_TXT .= "      Header set Cache-Control \"max-age=0, public\"\n";
        $FILE_TXT .= "  </FilesMatch>\n";
        $FILE_TXT .= "</IfModule>\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Redirect Error\n";
        $FILE_TXT .= "ErrorDocument 400 /?errCode=400\n";
        $FILE_TXT .= "ErrorDocument 401 /?errCode=401\n";
        $FILE_TXT .= "ErrorDocument 403 /?errCode=403\n";
        $FILE_TXT .= "ErrorDocument 404 /?errCode=404\n";
        $FILE_TXT .= "ErrorDocument 500 /?errCode=500\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Redirect Update\n";
        $FILE_TXT .= "Redirect /update/ /?updateApp=true\n";

        fwrite($FILE, $FILE_TXT);
        fclose($FILE);

    } 

    if (!file_exists($ROOT."/robots.txt")) {

        $FILE = fopen($ROOT."/robots.txt", "w");
        
        $FILE_TXT = "User-agent: *\n";
        $FILE_TXT .= "Disallow: /backend/\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "Sitemap: https://www.$PAGE->domain/shared/sitemap/sitemap.xml";

        fwrite($FILE, $FILE_TXT);
        fclose($FILE);
        
    }