<?php

    if (!file_exists($ROOT."/.htaccess")) {
        
        $FILE = fopen($ROOT."/.htaccess", "w");

        $FILE_TXT = "## Reindirizzamento forzato su www e https\n";
        $FILE_TXT .= "RewriteCond %{HTTPS} off\n";
        $FILE_TXT .= "RewriteRule .* https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
        $FILE_TXT .= "RewriteCond %{HTTP_HOST} !^www\.\n";
        $FILE_TXT .= "RewriteRule .* https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Abilita il caching del browser\n";
        $FILE_TXT .= "<IfModule mod_headers.c>\n";
        $FILE_TXT .= "  <FilesMatch \"\\.(jpe?g|png|gif|swf|flv|pdf|mp4|gz)$\">\n";
        $FILE_TXT .= "      Header set Cache-Control \"max-age=86400, public\"\n";
        $FILE_TXT .= "  </FilesMatch>\n";
        $FILE_TXT .= "  <FilesMatch \"\\.(js|css|html|htm|ico)$\">\n";
        $FILE_TXT .= "      Header set Cache-Control \"max-age=0, public\"\n";
        $FILE_TXT .= "  </FilesMatch>\n";
        $FILE_TXT .= " </IfModule>\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "## Redirect Error\n";
        $FILE_TXT .= "ErrorDocument 400 /error/?err=400\n";
        $FILE_TXT .= "ErrorDocument 401 /error/?err=401\n";
        $FILE_TXT .= "ErrorDocument 403 /error/?err=403\n";
        $FILE_TXT .= "ErrorDocument 404 /error/?err=404\n";
        $FILE_TXT .= "ErrorDocument 500 /error/?err=500\n";

        fwrite($FILE, $FILE_TXT);
        fclose($FILE);

    } 

    if (!file_exists($ROOT."/robots.txt")) {

        $FILE = fopen($ROOT."/robots.txt", "w");
        
        $FILE_TXT = "User-agent: *\n";
        $FILE_TXT .= "Disallow: /backend/\n";
        $FILE_TXT .= "\n";
        $FILE_TXT .= "Sitemap: $PATH->site/shared/sitemap/sitemap.xml";

        fwrite($FILE, $FILE_TXT);
        fclose($FILE);
        
    } 

?>