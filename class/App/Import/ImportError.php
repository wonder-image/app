<?php

namespace Wonder\App\Import;

use RuntimeException;

/**
 * Un errore scritto per chi carica il file: il messaggio arriva così com'è
 * nella pagina. Il testo viene da `import.errors.<chiave>` del core.
 */
final class ImportError extends RuntimeException
{
    /** @param array<string, scalar> $replacements */
    public static function make(string $key, array $replacements = []): self
    {
        return new self(self::translate($key, $replacements));
    }

    /** @param array<string, scalar> $replacements */
    public static function translate(string $key, array $replacements = []): string
    {
        $path = 'import.errors.'.$key;
        $text = function_exists('__t') ? __t($path, $replacements) : null;

        if (!is_string($text) || $text === '' || $text === $path) {
            $text = self::fallback($key);

            foreach ($replacements as $name => $value) {
                $text = str_replace('{{'.$name.'}}', (string) $value, $text);
            }
        }

        return $text;
    }

    private static function fallback(string $key): string
    {
        static $errors = null;

        if ($errors === null) {
            $json = json_decode((string) @file_get_contents(dirname(__DIR__, 3).'/resources/lang/it/import.json'), true);
            $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        }

        return (string) ($errors[$key] ?? $key);
    }
}
