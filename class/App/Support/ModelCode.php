<?php

namespace Wonder\App\Support;

use Throwable;

/**
 * Codice tecnico di una riga, con il prefisso dell'entità.
 *
 * `create_unique_code()` del framework c'è solo quando il sito è avviato: i
 * comandi di `forge` girano senza le funzioni globali. Qui c'è lo stesso
 * codice, con il ripiego che controlla l'unicità da sé.
 */
final class ModelCode
{
    private const LENGTH = 7;
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** @param class-string<\Wonder\App\Model> $modelClass */
    public static function make(string $modelClass, string $prefix, string $column = 'code'): string
    {
        if (function_exists('create_unique_code')) {
            return create_unique_code($modelClass::$table, $prefix, self::LENGTH, $column);
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = strtolower($prefix).self::random();

            if (!self::exists($modelClass, $column, $candidate)) {
                return $candidate;
            }
        }

        // Cinquanta collisioni di fila non capitano: se capitano, l'unicità
        // della colonna fermerà comunque l'inserimento.
        return strtolower($prefix).self::random();
    }

    private static function random(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Il Model sa parlare col database anche fuori dal sito avviato.
     *
     * Dove il database non c'è affatto (test degli schemi) il codice è per
     * forza libero: sette caratteri a caso non si scontrano con niente.
     */
    private static function exists(string $modelClass, string $column, string $candidate): bool
    {
        try {
            $row = $modelClass::find([$column => $candidate], 1);
        } catch (Throwable) {
            return false;
        }

        return is_array($row) && $row !== [];
    }
}
