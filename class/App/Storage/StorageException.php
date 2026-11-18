<?php

namespace Wonder\App\Storage;

use RuntimeException;

/** Errore di uno storage: percorso non valido, file mancante, scrittura fallita. */
class StorageException extends RuntimeException
{
}
