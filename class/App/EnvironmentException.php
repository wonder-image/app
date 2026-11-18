<?php

namespace Wonder\App;

use RuntimeException;

/**
 * `APP_ENV` manca o ha un valore non valido.
 *
 * Il messaggio dice quale valore è stato trovato, quali sono ammessi e dove
 * impostarlo; non contiene mai altri valori del `.env`.
 */
final class EnvironmentException extends RuntimeException
{
}
