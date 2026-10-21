<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `forge build`
 *
 * Genera i file statici necessari al routing del sito (front controller
 * `handler/index.php` e, se assente, `.htaccess`) **senza richiedere
 * connessione al database**.
 *
 * Pensato per essere eseguito sul runner di GitHub Actions PRIMA del
 * deploy FTP, per risolvere il bootstrap circolare del primo deploy:
 * il workflow chiama `/api/app/update/` su HTTP, ma quell'endpoint
 * passa per il front controller; senza `handler/index.php` sul server
 * il rewrite di `.htaccess` punta nel vuoto e Aruba/qualunque hosting
 * risponde 404.
 *
 * Differenza con `forge update --local`:
 * - `forge build` non carica `wonder-image.php`, non apre il DB, non
 *   esegue migration e non popola tabelle. Fa solo file generation.
 * - `forge update --local` fa quello che fa `build` + migration DB +
 *   build/row + build/update + build/cli; richiede DB connesso.
 *
 * Idempotente:
 * - `handler/index.php` viene sovrascritto sempre col template corrente
 *   (è generato da framework, niente di personalizzato dall'utente);
 * - `.htaccess` viene scritto solo se non esiste; se esiste e l'utente
 *   l'ha personalizzato (es. tolto la forza www), resta com'è.
 */
class Build extends Command
{
    public $name = 'build';

    protected function configure(): void
    {
        $this
            ->setName($this->name)
            ->setDescription('Genera handler/index.php e .htaccess senza richiedere DB. Per uso in CI prima del deploy.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sovrascrive anche .htaccess se esiste già')
            ->addOption('force-www', null, InputOption::VALUE_NONE, 'Aggiunge la regola force-WWW al template .htaccess (off di default: la maggior parte dei subdomain non ha www)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';

        // 1. Crea le cartelle di runtime se mancano.
        $directories = [
            $root.'/bin/',
            $root.'/assets/upload/user/profile-picture/',
            $root.'/storage/cache/',
            $root.'/storage/logs/',
            $root.'/storage/tmp/',
            $root.'/storage/generated/sitemap/',
            $root.'/handler/',
            $root.'/api/',
            $root.'/backend/',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (@mkdir($dir, self::runtimeDirectoryMode($dir), true) || is_dir($dir)) {
                    $output->writeln('<info>📁 Creato '.$dir.'</info>');
                } else {
                    $output->writeln('<comment>⚠️  Non sono riuscito a creare '.$dir.'</comment>');
                }
            }
        }

        self::writeScheduler($root);

        // 2. Pulisci endpoint legacy che ora vivono come route.
        $legacyPaths = [
            $root.'/update/page/index.php',
            $root.'/api/app/update/',
            $root.'/api/task/sitemap.php',
            $root.'/backend/account/',
        ];

        foreach ($legacyPaths as $path) {
            if (!file_exists($path)) {
                continue;
            }
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
            $output->writeln('<info>🧹 Rimosso path legacy '.$path.'</info>');
        }

        // 3. Genera il front controller.
        $handlerPath = $root.'/handler/index.php';
        $handlerContent = self::handlerTemplate();

        if (!is_dir($root.'/handler')) {
            @mkdir($root.'/handler', 0755, true);
        }

        $written = file_put_contents($handlerPath, $handlerContent);
        if ($written === false) {
            $output->writeln('<error>❌ Impossibile scrivere '.$handlerPath.'</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✅ Generato '.$handlerPath.'</info>');

        // 4. Genera .htaccess. Default: solo se manca (rispetta override
        //    dell'utente). Con --force lo sovrascrive sempre.
        $webroot = is_dir($root.'/public') ? $root.'/public' : $root;
        $htaccessPath = $webroot.'/.htaccess';

        $force = (bool) $input->getOption('force');
        $forceWww = (bool) $input->getOption('force-www');

        if (!file_exists($htaccessPath) || $force) {
            $htaccessContent = self::htaccessTemplate($forceWww, self::hstsEnabled());
            $written = file_put_contents($htaccessPath, $htaccessContent.PHP_EOL);
            if ($written === false) {
                $output->writeln('<error>❌ Impossibile scrivere '.$htaccessPath.'</error>');
                return Command::FAILURE;
            }
            $verb = file_exists($htaccessPath.'.bak') ? 'Sovrascritto' : 'Generato';
            $output->writeln('<info>✅ '.$verb.' '.$htaccessPath.($forceWww ? ' (con force-www)' : '').'</info>');
        } else {
            $output->writeln('<comment>↺ '.$htaccessPath.' esiste già, nessuna modifica. Usa --force per sovrascrivere.</comment>');
        }

        $uploadHtaccess = self::writeUploadHtaccess($root);
        if ($uploadHtaccess !== null) {
            $output->writeln('<info>✅ Aggiornato '.$uploadHtaccess.'</info>');
        }

        $output->writeln('<info>🧱 Build completato.</info>');

        return Command::SUCCESS;
    }

    /**
     * Template del front controller. Lo stesso che `app/build/cli/update.php`
     * scrive durante un update locale; tenuto qui in copia per evitare di
     * dover caricare `wonder-image.php` (che richiede DB) in CI.
     *
     * Se il template cambia in `update.php`, va aggiornato anche qui.
     */
    private static function handlerTemplate(): string
    {
        return <<<'PHP'
<?php

    $ROOT = dirname(__DIR__);
    require_once $ROOT.'/vendor/autoload.php';

    (new \Wonder\Http\RouteDispatcher($ROOT))->handleRequest();

PHP;
    }

    /**
     * Template `.htaccess` di default per shared hosting (Aruba, SiteGround,
     * IONOS, ecc.).
     *
     * Versione "no force-www": la maggior parte dei subdomini (es.
     * `test.example.it`) NON ha la corrispondente versione con www, quindi
     * forzare `www.` causerebbe redirect verso un host che non esiste e il
     * sito apparirebbe come 404 o pagina parking del provider.
     *
     * Per attivare force-www (utile per il dominio principale `example.it`
     * → `www.example.it`), passare `--force-www` a `forge build`.
     *
     * Aggiunte rispetto alla versione precedente:
     * - `Options +FollowSymLinks -MultiViews` (richiesto da molti shared
     *   hosting Apache per attivare `RewriteRule`);
     * - `RewriteBase /` (esplicito; alcune config Apache non assumono `/`
     *   come default su shared hosting);
     * - directory listing comunque disabilitato con `Options -Indexes`.
     */
    public static function htaccessTemplate(bool $forceWww = false, bool $hsts = false): string
    {
        $securityBlock = self::htaccessSecurityBlock($hsts);
        $performanceBlock = self::htaccessPerformanceBlock();
        $forceWwwBlock = $forceWww
            ? <<<'HTACCESS'

  # Forza WWW fuori dall'ambiente locale (opt-in via --force-www)
  RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:\d+)?$ [NC]
  RewriteCond %{HTTP_HOST} !^www\. [NC]
  RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
HTACCESS
            : '';

        return <<<HTACCESS
# ----------------------------------------------------------------------
# Apache options (necessari su molti shared hosting per il RewriteRule)
# ----------------------------------------------------------------------
Options +FollowSymLinks -MultiViews -Indexes
AddDefaultCharset UTF-8
DefaultLanguage it

$securityBlock

# ----------------------------------------------------------------------
# Routing
# ----------------------------------------------------------------------
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Passa Authorization/Bearer (CGI/FastCGI mangiano l'header senza questo)
  RewriteCond %{HTTP:Authorization} .
  RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

  # Forza HTTPS fuori dall'ambiente locale
  RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:\d+)?\$ [NC]
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
$forceWwwBlock

  # Le cartelle runtime /backend e /api esistono fisicamente, ma devono
  # comunque passare sempre dal router.
  RewriteCond %{REQUEST_URI} ^/(backend|api|oauth|\.well-known/oauth-[a-z-]+)(?:/.*)?\$ [NC]
  RewriteRule ^ handler/index.php [L,QSA]

  # La homepage deve sempre entrare nel router anche se la document root
  # esiste fisicamente come directory.
  RewriteRule ^\$ handler/index.php [L,QSA]

  # Aggiunge slash finale se mancante (solo per URL senza estensione)
  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?\$ [NC]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !/\$
  RewriteCond %{REQUEST_URI} !\.[^./]+\$
  RewriteRule ^(.*)\$ /\$1/ [R=301,L]

  # Router Wonder: tutto ciò che non è file/directory esistente va al
  # front controller handler/index.php
  RewriteCond %{REQUEST_URI} !^/handler(?:/.*)?\$ [NC]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ handler/index.php [L,QSA]
</IfModule>

# ----------------------------------------------------------------------
# Cache ottimizzata
# ----------------------------------------------------------------------
$performanceBlock
HTACCESS;
    }

    public static function writeScheduler(string $root): void
    {
        $directory = $root.'/bin';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare bin/');
        }
        $scheduler = <<<'PHP'
<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$ROOT = dirname(__DIR__);
require $ROOT.'/vendor/wonder-image/app/bin/scheduler.php';
PHP;
        if (file_put_contents($directory.'/scheduler.php', $scheduler.PHP_EOL) === false) {
            throw new \RuntimeException('Impossibile generare bin/scheduler.php');
        }
        $ignorePath = $root.'/.gitignore';
        $ignore = is_file($ignorePath) ? file_get_contents($ignorePath) : '';
        if ($ignore === false) {
            throw new \RuntimeException('Impossibile leggere .gitignore');
        }
        if (!preg_match('~^/?bin/scheduler\.php\s*$~m', $ignore)) {
            if (file_put_contents($ignorePath, rtrim($ignore)."\n\n# Generated Wonder scheduler\n/bin/scheduler.php\n") === false) {
                throw new \RuntimeException('Impossibile aggiornare .gitignore');
            }
        }
    }

    public static function htaccessPerformanceBlock(): string
    {
        return <<<'HTACCESS'
# WONDER PERFORMANCE START
<IfModule mod_headers.c>
  <FilesMatch "\.(jpe?g|png|gif|webp|avif|svg|ico|pdf|mp4|webm|ogg|woff2?|js|css)$">
    Header set Cache-Control "public, max-age=86400, must-revalidate"
    Header set Cache-Control "public, max-age=31536000, immutable" "expr=%{QUERY_STRING} =~ m#(^|&)v=[0-9]+(&|$)#"
  </FilesMatch>
  <FilesMatch "\.(html|htm)$">
    Header set Cache-Control "no-cache, must-revalidate"
  </FilesMatch>
</IfModule>

FileETag MTime Size

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 1 day"
  ExpiresByType image/png "access plus 1 day"
  ExpiresByType image/gif "access plus 1 day"
  ExpiresByType image/webp "access plus 1 day"
  ExpiresByType image/avif "access plus 1 day"
  ExpiresByType image/svg+xml "access plus 1 day"
  ExpiresByType text/css "access plus 1 day"
  ExpiresByType application/javascript "access plus 1 day"
  ExpiresByType text/html "access plus 0 seconds"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css text/javascript application/javascript application/json
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|avif|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-gzip dont-vary
</IfModule>

<IfModule mod_brotli.c>
  BrotliCompressionQuality 5
  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|avif|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-brotli dont-vary
</IfModule>
# WONDER PERFORMANCE END
HTACCESS;
    }

    public static function updateHtaccessPerformance(string $content): string
    {
        $block = self::htaccessPerformanceBlock();
        if (str_contains($content, '# WONDER PERFORMANCE START')) {
            return preg_replace_callback(
                '/# WONDER PERFORMANCE START.*?# WONDER PERFORMANCE END/s',
                static fn () => $block,
                $content
            ) ?? $content;
        }

        // Append overriding rules on legacy installations without deleting custom rules.
        return rtrim($content)."\n\n".$block."\n";
    }

    /**
     * Permessi delle cartelle create da Forge: 0750 per `storage/logs`, 0755
     * per le altre. Le cartelle esistenti non vengono toccate.
     */
    public static function runtimeDirectoryMode(string $dir): int
    {
        return str_ends_with(rtrim(str_replace('\\', '/', $dir), '/'), '/storage/logs') ? 0750 : 0755;
    }

    /** `APP_HSTS=true` abilita Strict-Transport-Security (opt-in). */
    public static function hstsEnabled(): bool
    {
        return \Wonder\Http\SecurityHeaders::hstsEnabled();
    }

    /**
     * Blocco gestito di sicurezza: header per gli asset statici (le risposte
     * PHP li ricevono da `Wonder\Http\SecurityHeaders`), deny di file e
     * cartelle che non devono mai essere serviti via HTTP.
     */
    public static function htaccessSecurityBlock(bool $hsts = false): string
    {
        $hstsLine = $hsts
            ? "\n    Header set Strict-Transport-Security \"max-age=31536000\" env=HTTPS"
            : '';

        return <<<HTACCESS
# WONDER SECURITY START
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  Header unset X-Powered-By
  # Solo file statici: per le pagine PHP gli header arrivano dal bootstrap.
  <FilesMatch "(?i)^(?!.*\\.php\$)">
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Content-Security-Policy "frame-ancestors 'self'"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Permissions-Policy "camera=(), microphone=(), geolocation=(self)"$hstsLine
  </FilesMatch>
</IfModule>

<FilesMatch "(?i)^(\\.env.*|\\.git.*|\\.ht.*|.*\\.log(\\.\\d+)?|.*\\.sql(\\.gz)?|.*\\.(bak|old|orig|swp)|.*~|composer\\.(json|lock)|package(-lock)?\\.json|skills-lock\\.json|sync-data\\.json|generator\\.conf|forge)\$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^(\\.git|\\.audit|storage|state|tests|docs|node_modules|lang|handler/storage)(/|\$) - [F,L,NC]
  # Archivi e backup negati ovunque tranne nei file caricati.
  RewriteRule ^(?!assets/upload/).*\\.(zip|tgz|tar(\\.gz)?)\$ - [F,L,NC]
  # vendor/ negata, tranne gli asset statici dei pacchetti e il generatore xml-sitemaps.
  RewriteRule ^vendor(/|\$)(?![^/]+/[^/]+/resources/assets/(?:[^/.]+/)*(?![^/]*\\.(?:php\\d?|phtml|phar|pht|phps|cgi|pl|py|sh)(?:\\.|\$))[^/]+\\.(?:css|js|map|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|svg|ico)\$|wonder-image/app/vendor-static/xml-sitemaps(/|\$)) - [F,L,NC]
</IfModule>
# WONDER SECURITY END
HTACCESS;
    }

    /**
     * Inserisce o aggiorna il blocco SECURITY senza toccare il resto del
     * file. Se manca va in testa, prima del router; i duplicati vengono
     * rimossi. Idempotente.
     */
    public static function updateHtaccessSecurity(string $content, bool $hsts = false): string
    {
        $eol = self::lineEnding($content);
        $block = self::withLineEnding(self::htaccessSecurityBlock($hsts), $eol);
        $pattern = '/# WONDER SECURITY START.*?# WONDER SECURITY END(\R?)/s';

        $content = self::removeLegacyHeaderBlock($content);
        $content = self::removeOrphanMarkers($content, '# WONDER SECURITY START', '# WONDER SECURITY END');

        if (preg_match($pattern, $content) === 1) {
            $first = true;

            return preg_replace_callback($pattern, static function (array $m) use (&$first, $block): string {
                if (!$first) {
                    return '';
                }
                $first = false;

                return $block.$m[1];
            }, $content) ?? $content;
        }

        return $block.$eol.$eol.ltrim($content);
    }

    /**
     * Righe `Header always set` fuori dai blocchi gestiti: sulle risposte PHP
     * si sommano agli header di `SecurityHeaders`, vanno tolte a mano.
     *
     * @return list<string>
     */
    public static function unmanagedHeaderSetLines(string $content): array
    {
        $outside = preg_replace('/# WONDER ([A-Z]+) START.*?# WONDER \1 END/s', '', $content) ?? $content;
        preg_match_all('/^[ \t]*Header[ \t]+always[ \t]+set[ \t].*$/mi', $outside, $m);

        return array_map(static fn (string $l): string => trim($l), $m[0]);
    }

    /** `\r\n` se il file lo usa, altrimenti `\n`. */
    private static function lineEnding(string $content): string
    {
        return str_contains($content, "\r\n") ? "\r\n" : "\n";
    }

    private static function withLineEnding(string $block, string $eol): string
    {
        return $eol === "\n" ? $block : str_replace("\n", $eol, $block);
    }

    /**
     * Toglie le sole righe marcatore senza coppia (START senza END, END senza
     * START): il testo in mezzo resta, così il match successivo non lo inghiotte.
     * Marcatore = riga che lo contiene, come nei pattern di update (anche con testo dopo).
     */
    private static function removeOrphanMarkers(string $content, string $start, string $end): string
    {
        $lines = preg_split('/(?<=\n)/', $content) ?: [];
        $open = null;
        $orphans = [];

        foreach ($lines as $i => $line) {
            if (str_contains($line, $start)) {
                if ($open !== null) {
                    $orphans[] = $open;
                }
                $open = $i;
            } elseif (str_contains($line, $end)) {
                if ($open === null) {
                    $orphans[] = $i;
                }
                $open = null;
            }
        }

        if ($open !== null) {
            $orphans[] = $open;
        }

        foreach ($orphans as $i) {
            unset($lines[$i]);
        }

        return implode('', $lines);
    }

    /**
     * Toglie il blocco header non marcato generato dalle versioni precedenti,
     * solo se identico all'originale (righe custom = file non toccato).
     */
    private static function removeLegacyHeaderBlock(string $content): string
    {
        $lines = [
            'Header always set X-Frame-Options "SAMEORIGIN"',
            'Header always set X-Content-Type-Options "nosniff"',
            'Header always set Referrer-Policy "strict-origin-when-cross-origin"',
            'Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"',
            'Header always set X-XSS-Protection "1; mode=block"',
        ];
        $body = implode('\s*\R\s*', array_map(static fn (string $l): string => preg_quote($l, '/'), $lines));
        $pattern = '/\R?[ \t]*<IfModule mod_headers\.c>\s*\R\s*'.$body.'\s*\R\s*<\/IfModule>[ \t]*/';

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /** Blocco gestito di `assets/upload/.htaccess`: niente PHP, niente `.ht*`. */
    public static function uploadHtaccessBlock(): string
    {
        return <<<'HTACCESS'
# WONDER UPLOAD START
Options -Indexes -ExecCGI
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<IfModule mod_php7.c>
  php_flag engine off
</IfModule>
<IfModule mod_php8.c>
  php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .phps .pht
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .phps .pht
<FilesMatch "(?i)(\.(php\d?|phtml|phar|phps|pht|cgi|pl|py|sh)(\.|$)|^\.ht)">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
</IfModule>
<FilesMatch "(?i)\.svgz?$">
  <IfModule mod_headers.c>
    Header set Content-Security-Policy "sandbox"
  </IfModule>
</FilesMatch>
# WONDER UPLOAD END
HTACCESS;
    }

    /** Inserisce o aggiorna il blocco UPLOAD preservando le righe custom. Idempotente. */
    public static function updateUploadHtaccess(string $content): string
    {
        $eol = self::lineEnding($content);
        $block = self::withLineEnding(self::uploadHtaccessBlock(), $eol);
        $pattern = '/# WONDER UPLOAD START.*?# WONDER UPLOAD END/s';
        $content = self::removeOrphanMarkers($content, '# WONDER UPLOAD START', '# WONDER UPLOAD END');

        if (preg_match($pattern, $content) === 1) {
            $first = true;

            return preg_replace_callback($pattern, static function () use (&$first, $block): string {
                if (!$first) {
                    return '';
                }
                $first = false;

                return $block;
            }, $content) ?? $content;
        }

        $content = trim($content);

        return $content === '' ? $block.$eol : $content.$eol.$eol.$block.$eol;
    }

    /**
     * Scrive `assets/upload/.htaccess` solo se cambia. Ritorna il percorso
     * scritto, `null` se non c'era nulla da fare.
     */
    public static function writeUploadHtaccess(string $root): ?string
    {
        $dir = rtrim($root, '/').'/assets/upload';
        if (!is_dir($dir)) {
            return null;
        }

        $path = $dir.'/.htaccess';
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        $updated = self::updateUploadHtaccess($current);
        if ($updated === $current) {
            return null;
        }

        return file_put_contents($path, $updated) === false ? null : $path;
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                self::removeDirectory($entry);
            } else {
                @unlink($entry);
            }
        }

        @rmdir($dir);
    }
}
