<?php

namespace Wonder\App\Storage;

/**
 * Un luogo dove stanno i file caricati (disco locale oggi, S3 domani).
 *
 * I percorsi sono relativi alla radice dello storage, con `/` come separatore,
 * senza `/` iniziale e senza `.` o `..` (`StoragePath::normalize()`): chi
 * chiama non conosce mai il percorso fisico né il prefisso di tenant e
 * ambiente. Un percorso non valido lancia `StorageException`.
 *
 * Un file che non c'è non è un errore per `exists()`, `delete()` e `list()`;
 * lo è per `read()`, `size()`, `lastModified()`, `copy()` e `move()`.
 */
interface Storage
{
    public function exists(string $path): bool;

    /** Contenuto del file. */
    public function read(string $path): string;

    /** Scrive il file in modo atomico (niente file a metà), creando le cartelle. */
    public function write(string $path, string $contents): void;

    /**
     * Porta in `$path` un file locale (un upload, un file temporaneo). Con
     * `$move` il file locale sparisce; un upload di PHP si sposta con
     * `move_uploaded_file()`.
     */
    public function writeFile(string $path, string $localFile, bool $move = false): void;

    /** Vero se dopo la chiamata il file non c'è più (anche se non c'era). */
    public function delete(string $path): bool;

    public function copy(string $from, string $to): void;

    public function move(string $from, string $to): void;

    /**
     * Percorsi dei file sotto `$directory` (non delle cartelle), ordinati.
     *
     * @return list<string>
     */
    public function list(string $directory = '', bool $recursive = false): array;

    public function size(string $path): int;

    /** Timestamp Unix dell'ultima modifica. */
    public function lastModified(string $path): int;

    /** Indirizzo pubblico del file, senza controllare che esista. */
    public function url(string $path): string;

    /**
     * Percorso fisico del file, per chi lavora su un file vero (GD,
     * Intervention, FPDF). Null se il driver non è su disco locale: chi
     * chiama allora usa `read()` e un file temporaneo.
     */
    public function localPath(string $path): ?string;
}
