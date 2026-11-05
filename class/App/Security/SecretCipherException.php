<?php

namespace Wonder\App\Security;

use RuntimeException;

/** Errore di cifratura o decifratura: il messaggio non contiene mai valori. */
final class SecretCipherException extends RuntimeException
{
}
