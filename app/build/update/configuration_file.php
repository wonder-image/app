<?php

    $WEBROOT = is_dir($ROOT."/public") ? $ROOT."/public" : $ROOT;
    $HTACCESS_PATH = $WEBROOT."/.htaccess";
    $ROBOTS_PATH = $WEBROOT."/robots.txt";

    // Il blocco router viene usato per aggiornare .htaccess esistenti
    // senza sovrascrivere personalizzazioni dell'utente.
    $ROUTER_BLOCK = "# WONDER ROUTER START\n";
    $ROUTER_BLOCK .= "  # Le cartelle runtime /backend e /api esistono fisicamente, ma devono\n";
    $ROUTER_BLOCK .= "  # comunque passare sempre dal router.\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} ^/(backend|api)(?:/.*)?$ [NC]\n";
    $ROUTER_BLOCK .= "  RewriteRule ^ handler/index.php [L,QSA]\n\n";
    $ROUTER_BLOCK .= "  RewriteRule ^$ handler/index.php [L,QSA]\n\n";
    $ROUTER_BLOCK .= "  # Aggiunge slash finale se mancante (solo per URL senza estensione)\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} !/$\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} !\\.[^./]+$\n";
    $ROUTER_BLOCK .= "  RewriteRule ^(.*)$ /$1/ [R=301,L]\n\n";
    $ROUTER_BLOCK .= "  # Router Wonder\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?$ [NC]\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-f\n";
    $ROUTER_BLOCK .= "  RewriteCond %{REQUEST_FILENAME} !-d\n";
    $ROUTER_BLOCK .= "  RewriteRule ^ handler/index.php [L,QSA]\n";
    $ROUTER_BLOCK .= "# WONDER ROUTER END";

    // --- .htaccess ---
    // Se esiste già: aggiorna solo il blocco WONDER ROUTER (preserva
    // personalizzazioni dell'utente). Se non esiste: crea da template
    // unico (Build::htaccessTemplate), con force-www parametrico.

    if (file_exists($HTACCESS_PATH)) {

        $htaccessContent = file_get_contents($HTACCESS_PATH);

        if (is_string($htaccessContent) && $htaccessContent !== '') {

            $updatedContent = $htaccessContent;
            $routerInserted = false;

            // Sostituisce il vecchio redirect legacy con il blocco router completo
            $updatedContent = preg_replace(
                '/^Redirect 301 \/update\/ \/\?updateApp=true\r?\n/m',
                $ROUTER_BLOCK."\n",
                $updatedContent,
                1,
                $legacyRedirectCount
            );

            if ($legacyRedirectCount > 0) {
                $routerInserted = true;
            }

            // Se esiste già un blocco Wonder Router, lo sostituisce sempre con quello aggiornato
            $updatedContent = preg_replace(
                '/\n?# WONDER ROUTER START.*?# WONDER ROUTER END\r?\n?/s',
                "\n".$ROUTER_BLOCK."\n",
                (string) $updatedContent,
                1,
                $routerBlockCount
            );

            if ($routerBlockCount > 0) {
                $routerInserted = true;
            }

            // Se non c'era né redirect legacy né blocco router, aggiunge il router in fondo
            if (!$routerInserted) {
                $updatedContent = rtrim((string) $updatedContent)."\n\n".$ROUTER_BLOCK."\n";
            }

            // Pulizia: evita troppe righe vuote consecutive
            $updatedContent = preg_replace("/\n{3,}/", "\n\n", (string) $updatedContent);

            if (is_string($updatedContent) && $updatedContent !== $htaccessContent) {
                file_put_contents($HTACCESS_PATH, $updatedContent);
            }
        }
    }

    if (!file_exists($HTACCESS_PATH)) {

        // Single source of truth: Build::htaccessTemplate().
        // Il flag force-www viene letto dalla variabile d'ambiente
        // APP_FORCE_WWW (impostata nel .env o nelle GitHub Actions vars).
        $forceWww = filter_var($_ENV['APP_FORCE_WWW'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $htaccessContent = \Wonder\Console\Commands\Build::htaccessTemplate($forceWww);

        file_put_contents($HTACCESS_PATH, $htaccessContent.PHP_EOL);

    }

    // --- robots.txt ---
    // Creato solo se manca. Il dominio viene letto da $PAGE->domain
    // (disponibile in tutti gli ambienti). Il prefisso www. viene
    // determinato da APP_FORCE_WWW.

    if (!file_exists($ROBOTS_PATH)) {

        $domain = $PAGE->domain ?? ($_ENV['APP_DOMAIN'] ?? 'localhost');
        $forceWww = filter_var($_ENV['APP_FORCE_WWW'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $wwwPrefix = $forceWww ? 'www.' : '';

        $FILE_CONTENT = "User-agent: *\n";
        $FILE_CONTENT .= "Disallow: /backend/\n";
        $FILE_CONTENT .= "\n";
        $FILE_CONTENT .= "Sitemap: https://{$wwwPrefix}{$domain}/shared/sitemap/sitemap.xml\n";

        file_put_contents($ROBOTS_PATH, $FILE_CONTENT);

    }

    // --- Driver globale per Herd/Valet ---
    $HERD_HOME = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');
    $VALET_DRIVERS_DIR = rtrim($HERD_HOME, DIRECTORY_SEPARATOR).'/Library/Application Support/Herd/config/valet/Drivers';
    $VALET_DRIVER_PATH = $VALET_DRIVERS_DIR.'/WonderValetDriver.php';
    $VALET_DRIVER_STUB_PATH = $ROOT_APP . '/build/stubs/WonderValetDriver.php';

    if (file_exists($VALET_DRIVER_STUB_PATH)) {
        if (!is_dir($VALET_DRIVERS_DIR)) {
            @mkdir($VALET_DRIVERS_DIR, 0777, true);
        }

        $valetDriver = file_get_contents($VALET_DRIVER_STUB_PATH);

        if (is_string($valetDriver) && trim($valetDriver) !== '') {
            $currentDriver = file_exists($VALET_DRIVER_PATH)
                ? file_get_contents($VALET_DRIVER_PATH)
                : false;

            if ($currentDriver !== $valetDriver) {
                file_put_contents($VALET_DRIVER_PATH, $valetDriver);
            }
        }
    }
