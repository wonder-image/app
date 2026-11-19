<?php

namespace Wonder\App\Storage;

/**
 * Mette un prefisso (`{tenant}/{ambiente}/`) davanti a ogni percorso prima di
 * passarlo al driver: chi usa lo storage lavora sempre con percorsi relativi
 * al sito e non vede mai il prefisso.
 */
final class PrefixedStorage implements Storage
{
    private string $prefix;

    public function __construct(private readonly Storage $inner, string $prefix)
    {
        $this->prefix = StoragePath::normalize($prefix);

        if ($this->prefix === '') {
            throw new StorageException('Prefisso vuoto: usa direttamente il driver.');
        }
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($this->key($path));
    }

    public function read(string $path): string
    {
        return $this->inner->read($this->key($path));
    }

    public function write(string $path, string $contents): void
    {
        $this->inner->write($this->key($path), $contents);
    }

    public function writeFile(string $path, string $localFile, bool $move = false): void
    {
        $this->inner->writeFile($this->key($path), $localFile, $move);
    }

    public function delete(string $path): bool
    {
        return $this->inner->delete($this->key($path));
    }

    public function copy(string $from, string $to): void
    {
        $this->inner->copy($this->key($from), $this->key($to));
    }

    public function move(string $from, string $to): void
    {
        $this->inner->move($this->key($from), $this->key($to));
    }

    public function list(string $directory = '', bool $recursive = false): array
    {
        $length = strlen($this->prefix) + 1;

        return array_values(array_map(
            static fn (string $path): string => substr($path, $length),
            $this->inner->list($this->prefix.'/'.StoragePath::normalize($directory), $recursive)
        ));
    }

    public function size(string $path): int
    {
        return $this->inner->size($this->key($path));
    }

    public function lastModified(string $path): int
    {
        return $this->inner->lastModified($this->key($path));
    }

    public function url(string $path): string
    {
        return $this->inner->url($this->key($path));
    }

    public function localPath(string $path): ?string
    {
        return $this->inner->localPath($this->key($path));
    }

    private function key(string $path): string
    {
        return $this->prefix.'/'.StoragePath::file($path);
    }
}
