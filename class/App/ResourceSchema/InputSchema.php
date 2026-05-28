<?php

namespace Wonder\App\ResourceSchema;

use Exception;
use Wonder\App\ResourceSchema\Inputs\InputPassword;
use Wonder\App\ResourceSchema\Inputs\InputText;

/**
 * Factory-dispatcher per le classi `Input*` migrate, mirror esatto di
 * `Wonder\Data\UploadSchema` (class/Data/UploadSchema.php).
 *
 *   InputSchema::key('email')->text()->required()
 *   InputSchema::key('password')->password()->minLength(8)->requireSpecial()
 *
 * Per i tipi non ancora estratti in `Inputs/` si continua a usare
 * `FormField::key(...)`. Il `FormFieldElementFactory` accetta entrambe le
 * famiglie (type hint comune `Input`), quindi non c'è migrazione forzata.
 */
class InputSchema
{
    /** @var array<string, class-string<Input>> */
    private const INPUTS = [
        'text' => InputText::class,
        'password' => InputPassword::class,
    ];

    public function __construct(public string $key)
    {
    }

    public static function key(string $key): static
    {
        return new self($key);
    }

    /**
     * Proxy magico: `InputSchema::key('p')->password()` istanzia
     * `InputPassword`, `->text()` istanzia `InputText`, ecc.
     */
    public function __call(string $method, array $args): Input
    {
        $class = self::INPUTS[strtolower($method)] ?? null;

        if ($class === null) {
            throw new Exception("Input {$method} non supportato in InputSchema. Tipi disponibili: ".implode(', ', array_keys(self::INPUTS)));
        }

        return new $class($this->key);
    }
}
