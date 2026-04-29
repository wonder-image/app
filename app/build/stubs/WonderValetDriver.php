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

        if ($uri !== '' && $uri !== '/' && $this->isActualFile($handler)) {
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
}
