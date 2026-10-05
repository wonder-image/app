<?php

namespace Wonder\Elements\Concerns;

use InvalidArgumentException;

/**
 * La conferma che la lib chiede prima di un'azione (`data-wi-confirm`).
 *
 * Le chiavi sono le stesse nello schema del Button e nelle opzioni di una
 * voce del Dropdown: `confirm` (il testo), `confirm_title`, `confirm_ok`,
 * `confirm_variant`. Le scrive `Themes\Concerns\RendersPostForm`.
 */
trait HasConfirmation
{
    /** @return array<string, string> le chiavi vuote restano fuori */
    protected function confirmationSchema(
        string $message,
        ?string $title = null,
        ?string $ok = null,
        ?string $variant = null
    ): array {
        $message = trim($message);

        if ($message === '') {
            return [];
        }

        $variant = trim((string) $variant);

        if ($variant !== '' && !preg_match('/^[a-z][a-z0-9-]*$/i', $variant)) {
            throw new InvalidArgumentException('Variante della conferma non valida: lettere, numeri e trattini.');
        }

        return array_filter([
            'confirm' => $message,
            'confirm_title' => trim((string) $title),
            'confirm_ok' => trim((string) $ok),
            'confirm_variant' => $variant,
        ], static fn (string $value): bool => $value !== '');
    }
}
