<?php

namespace Wonder\App\Storage;

use Throwable;

/**
 * Storage su disco locale: una cartella radice e l'indirizzo con cui il web
 * server la serve. Con `$baseUrl` null la radice non è pubblica e `url()`
 * lancia `StorageException`; con `''` gli indirizzi sono relativi al sito.
 */
final class LocalStorage implements Storage
{
    private string $root;
    private ?string $baseUrl;

    public function __construct(string $root, ?string $baseUrl = '', private readonly int $directoryMode = 0775)
    {
        $this->root = rtrim($root, '/');
        $this->baseUrl = $baseUrl === null ? null : rtrim($baseUrl, '/');

        if ($this->root === '') {
            throw new StorageException('Radice dello storage mancante.');
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->full($path));
    }

    public function read(string $path): string
    {
        $full = $this->existing($path);
        $contents = @file_get_contents($full);

        if ($contents === false) {
            throw new StorageException('Lettura non riuscita: '.StoragePath::file($path));
        }

        return $contents;
    }

    public function write(string $path, string $contents): void
    {
        $full = $this->full($path);
        $this->ensureDirectory(dirname($full));
        $temporary = $this->temporary($full);

        try {
            if (@file_put_contents($temporary, $contents) === false || !@rename($temporary, $full)) {
                throw new StorageException('Scrittura non riuscita: '.StoragePath::file($path));
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function writeFile(string $path, string $localFile, bool $move = false): void
    {
        if (!is_file($localFile)) {
            throw new StorageException('File locale non trovato.');
        }

        $full = $this->full($path);
        $this->ensureDirectory(dirname($full));
        $temporary = $this->temporary($full);

        try {
            $done = $move && is_uploaded_file($localFile)
                ? @move_uploaded_file($localFile, $temporary)
                : @copy($localFile, $temporary);

            if (!$done || !@rename($temporary, $full)) {
                throw new StorageException('Scrittura non riuscita: '.StoragePath::file($path));
            }

            if ($move && is_file($localFile)) {
                @unlink($localFile);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function delete(string $path): bool
    {
        $full = $this->full($path);

        return !is_file($full) || @unlink($full);
    }

    public function copy(string $from, string $to): void
    {
        $this->writeFile($to, $this->existing($from));
    }

    public function move(string $from, string $to): void
    {
        $source = $this->existing($from);
        $target = $this->full($to);
        $this->ensureDirectory(dirname($target));

        if (!@rename($source, $target)) {
            throw new StorageException('Spostamento non riuscito: '.StoragePath::file($from));
        }
    }

    public function list(string $directory = '', bool $recursive = false): array
    {
        $relative = StoragePath::normalize($directory);
        $base = $relative === '' ? $this->root : $this->root.'/'.$relative;

        if (!is_dir($base)) {
            return [];
        }

        $found = [];
        $this->collect($base, $relative, $recursive, $found);
        sort($found);

        return $found;
    }

    public function size(string $path): int
    {
        return (int) filesize($this->existing($path));
    }

    public function lastModified(string $path): int
    {
        return (int) filemtime($this->existing($path));
    }

    public function url(string $path): string
    {
        if ($this->baseUrl === null) {
            throw new StorageException('Questo storage non ha indirizzi pubblici.');
        }

        return $this->baseUrl.'/'.StoragePath::file($path);
    }

    public function localPath(string $path): ?string
    {
        return $this->full($path);
    }

    private function full(string $path): string
    {
        return $this->root.'/'.StoragePath::file($path);
    }

    private function existing(string $path): string
    {
        $full = $this->full($path);

        if (!is_file($full)) {
            throw new StorageException('File non trovato: '.StoragePath::file($path));
        }

        return $full;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, $this->directoryMode, true) && !is_dir($directory)) {
            throw new StorageException('Cartella non creabile.');
        }
    }

    private function temporary(string $full): string
    {
        return dirname($full).'/.'.basename($full).'.'.bin2hex(random_bytes(4)).'.tmp';
    }

    /** @param list<string> $found */
    private function collect(string $directory, string $relative, bool $recursive, array &$found): void
    {
        try {
            $entries = scandir($directory) ?: [];
        } catch (Throwable) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || (str_starts_with($entry, '.') && str_ends_with($entry, '.tmp'))) {
                continue;
            }

            $full = $directory.'/'.$entry;
            $path = $relative === '' ? $entry : $relative.'/'.$entry;

            if (is_dir($full)) {
                if ($recursive) {
                    $this->collect($full, $path, true, $found);
                }
            } else {
                $found[] = $path;
            }
        }
    }
}
