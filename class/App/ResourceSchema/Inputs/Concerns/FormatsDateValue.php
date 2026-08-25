<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Normalizzazione del value dei tipi data prima di consegnarlo all'Element:
 * ogni controllo si aspetta il proprio formato (`Y-m-d` per il date nativo,
 * `d/m/Y` per il picker della lib, `Y-m-d\TH:i` per il datetime-local).
 *
 * Un value non interpretabile viene lasciato passare così com'è: meglio un
 * campo che mostra il dato grezzo che un campo svuotato in silenzio.
 */
trait FormatsDateValue
{
    protected function formatDateValue(mixed $value, string $format): mixed
    {
        if (!is_scalar($value)) {
            return $value;
        }

        return $this->formatDateString((string) $value, $format) ?? $value;
    }

    protected function formatDateString(string $value, string $format): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date($format, $timestamp);
    }
}
