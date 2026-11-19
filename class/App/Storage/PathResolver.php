<?php

namespace Wonder\App\Storage;

/**
 * Decide dove va un percorso relativo dentro un driver.
 *
 * - `flat` (predefinito): il percorso resta com'è, quindi i file e gli
 *   indirizzi dei siti esistenti non cambiano (`assets/upload/images/a.jpg`).
 * - `scoped`: davanti si mette `{tenant}/{ambiente}/`, come nel bucket S3.
 *
 * Il tenant e l'ambiente sono comunque sempre noti allo storage anche quando
 * il layout è `flat`: servono alla ricerca a due livelli e al passaggio a S3.
 */
final class PathResolver
{
    public const FLAT = 'flat';
    public const SCOPED = 'scoped';

    public function __construct(private readonly string $layout, private readonly StorageScope $scope)
    {
        if (!in_array($layout, [self::FLAT, self::SCOPED], true)) {
            throw new StorageException('Layout dello storage non valido: '.self::FLAT.' o '.self::SCOPED.'.');
        }
    }

    public function layout(): string
    {
        return $this->layout;
    }

    public function scope(): StorageScope
    {
        return $this->scope;
    }

    /** Stesso layout e tenant, altro ambiente (per leggere lo spazio di produzione). */
    public function forEnvironment(string $environment): self
    {
        return new self($this->layout, $this->scope->inEnvironment($environment));
    }

    /** Prefisso del driver, `''` per `flat`, `{tenant}/{ambiente}` per `scoped`. */
    public function prefix(): string
    {
        return $this->layout === self::SCOPED ? $this->scope->prefix() : '';
    }

    /** Percorso nel driver di un percorso relativo al sito. */
    public function key(string $relative): string
    {
        $relative = StoragePath::file($relative);
        $prefix = $this->prefix();

        return $prefix === '' ? $relative : $prefix.'/'.$relative;
    }

    /** Il driver visto dal sito: lo stesso se `flat`, con il prefisso se `scoped`. */
    public function apply(Storage $driver): Storage
    {
        $prefix = $this->prefix();

        return $prefix === '' ? $driver : new PrefixedStorage($driver, $prefix);
    }
}
