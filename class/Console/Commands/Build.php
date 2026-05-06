<?php

namespace Wonder\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setDescription('Genera handler/index.php e .htaccess senza richiedere DB. Per uso in CI prima del deploy.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = getcwd() ?: '.';

        // 1. Crea le cartelle di runtime se mancano.
        $directories = [
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
                if (@mkdir($dir, 0777, true) || is_dir($dir)) {
                    $output->writeln('<info>📁 Creato '.$dir.'</info>');
                } else {
                    $output->writeln('<comment>⚠️  Non sono riuscito a creare '.$dir.'</comment>');
                }
            }
        }

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
            @mkdir($root.'/handler', 0777, true);
        }

        $written = file_put_contents($handlerPath, $handlerContent);
        if ($written === false) {
            $output->writeln('<error>❌ Impossibile scrivere '.$handlerPath.'</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✅ Generato '.$handlerPath.'</info>');

        // 4. Genera .htaccess SOLO se manca (rispetta override dell'utente).
        $webroot = is_dir($root.'/public') ? $root.'/public' : $root;
        $htaccessPath = $webroot.'/.htaccess';

        if (!file_exists($htaccessPath)) {
            $htaccessContent = self::htaccessTemplate();
            $written = file_put_contents($htaccessPath, $htaccessContent.PHP_EOL);
            if ($written === false) {
                $output->writeln('<error>❌ Impossibile scrivere '.$htaccessPath.'</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>✅ Generato '.$htaccessPath.'</info>');
        } else {
            $output->writeln('<comment>↺ '.$htaccessPath.' esiste già, nessuna modifica.</comment>');
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
     * Template `.htaccess` di default. Stesso contenuto generato da
     * `app/build/update/configuration_file.php` quando `.htaccess` manca.
     * Tenuto in copia per la stessa ragione del front controller.
     */
    private static function htaccessTemplate(): string
    {
        return <<<'HTACCESS'
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
  <FilesMatch "\.(jpe?g|png|gif|svg|ico|pdf|mp4|webm|ogg|woff2?)$">
    Header set Cache-Control "public, max-age=86400, must-revalidate"
  </FilesMatch>
  <FilesMatch "\.(js|css)$">
    Header set Cache-Control "public, max-age=604800, must-revalidate"
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
  ExpiresByType image/svg+xml "access plus 1 day"
  ExpiresByType text/css "access plus 1 week"
  ExpiresByType application/javascript "access plus 1 week"
  ExpiresByType text/html "access plus 0 seconds"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css text/javascript application/javascript application/json
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-gzip dont-vary
</IfModule>

<IfModule mod_brotli.c>
  BrotliCompressionQuality 5
  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json
  SetEnvIfNoCase Request_URI "\.(?:gif|jpe?g|png|webp|ico|pdf|mp4|mp3|mov|avi|zip|gz|woff2?)$" no-brotli dont-vary
</IfModule>

<IfModule mod_rewrite.c>
  RewriteCond %{ENV:REDIRECT_STATUS} >=400
  RewriteCond %{ENV:REDIRECT_STATUS} <600
  RewriteCond %{QUERY_STRING} !errCode=
  RewriteRule ^.*$ /?errCode=%{ENV:REDIRECT_STATUS} [R=302,L]
</IfModule>

<IfModule mod_headers.c>
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
  Header always set X-XSS-Protection "1; mode=block"
</IfModule>

Options -Indexes
AddDefaultCharset UTF-8
DefaultLanguage it
HTACCESS;
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
