<?php

namespace Valet\Drivers\Custom;

use Valet\Drivers\ValetDriver;

class WonderValetDriver extends ValetDriver
{
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return file_exists($sitePath.'/handler/index.php');
    }

    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        if (file_exists($staticFilePath = $sitePath.rtrim($uri, '/').'/index.html')) {
            return $staticFilePath;
        }

        if ($this->isActualFile($staticFilePath = $sitePath.$uri)) {
            return $staticFilePath;
        }

        if ($this->shouldProxyMedia($sitePath, $uri)) {
            return $this->mediaProxyScript($sitePath, $uri);
        }

        return false;
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $uri = rtrim($uri, '/');
        $handler = $sitePath.'/handler/index.php';

        $candidates = [
            $sitePath.$uri,
            $sitePath.$uri.'/index.php',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isActualFile($candidate)) {
                $_SERVER['SCRIPT_FILENAME'] = $candidate;
                $_SERVER['SCRIPT_NAME'] = str_replace($sitePath, '', $candidate);
                $_SERVER['DOCUMENT_ROOT'] = $sitePath;

                return $candidate;
            }
        }

        if ($this->isActualFile($handler)) {
            $_SERVER['SCRIPT_FILENAME'] = $handler;
            $_SERVER['SCRIPT_NAME'] = '/handler/index.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath;

            return $handler;
        }

        foreach ([
            $sitePath.'/index.php',
            $sitePath.'/index.html',
        ] as $candidate) {
            if ($this->isActualFile($candidate)) {
                $_SERVER['SCRIPT_FILENAME'] = $candidate;
                $_SERVER['SCRIPT_NAME'] = str_replace($sitePath, '', $candidate);
                $_SERVER['DOCUMENT_ROOT'] = $sitePath;

                return $candidate;
            }
        }

        return null;
    }

    private function shouldProxyMedia(string $sitePath, string $uri): bool
    {
        if (!str_starts_with($uri, '/assets/upload/')) {
            return false;
        }

        if ($this->isActualFile($sitePath.$uri)) {
            return false;
        }

        return $this->mediaFallbackUrl($sitePath) !== '';
    }

    private function mediaFallbackUrl(string $sitePath): string
    {
        static $cache = [];

        if (isset($cache[$sitePath])) {
            return $cache[$sitePath];
        }

        $envPath = $sitePath.'/.env';

        if (!file_exists($envPath)) {
            return $cache[$sitePath] = '';
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $cache[$sitePath] = '';
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_starts_with($line, 'MEDIA_FALLBACK_URL=')) {
                $value = trim(substr($line, strlen('MEDIA_FALLBACK_URL=')));
                return $cache[$sitePath] = rtrim($value, '/');
            }
        }

        return $cache[$sitePath] = '';
    }

    private function mediaProxyScript(string $sitePath, string $uri): string
    {
        $fallbackUrl = $this->mediaFallbackUrl($sitePath);
        $targetUrl = $fallbackUrl.$uri;

        $tmpDir = $sitePath.'/storage/tmp';

        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $script = $tmpDir.'/_media_proxy.php';
        $escaped = addslashes($targetUrl);

        file_put_contents($script, "<?php header('Location: {$escaped}', true, 302); exit;");

        return $script;
    }
}
