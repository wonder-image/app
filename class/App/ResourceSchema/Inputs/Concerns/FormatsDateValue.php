<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Normalizzazione del value dei tipi data prima di consegnarlo all'Element:
 * ogni controllo si aspetta il proprio formato (`Y-m-d` per il date nativo,
 * `d/m/Y` per il picker della lib, `Y-m-d\TH:i` per il datetime-local).
 *
 * Una data con le barre si legge all'italiana (`d/m/Y`), come la scrive il
 * picker: `strtotime()` da solo la leggerebbe `m/d/Y` e scambierebbe giorno e
 * mese a ogni form ripresentato.
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

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(.*)$#s', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $value = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]).$m[4];
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date($format, $timestamp);
    }
}
