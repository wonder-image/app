<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** ID del contenitore e interruttore: il tag parte solo col consenso alle statistiche. */
final class GoogleTagManager extends Integration
{
    public function key(): string
    {
        return 'google-tag-manager';
    }

    public function name(): string
    {
        return 'Google Tag Manager';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-tags';
    }

    public function description(): string
    {
        return 'Contenitore dei tag di statistica e marketing del sito.';
    }

    public function fields(): array
    {
        return [
            'g_tag_manager_id' => 8,
            'g_tag_manager_active' => 4,
        ];
    }

    public function modes(): array
    {
        return ['g_tag_manager_active'];
    }

    public function note(array $values): ?string
    {
        return self::filled($values['g_tag_manager_id'] ?? null) && !self::active($values['g_tag_manager_active'] ?? null)
            ? 'Spento'
            : null;
    }

    public function order(): int
    {
        return 60;
    }

    public function docsUrl(): ?string
    {
        return 'https://support.google.com/tagmanager/answer/6103696';
    }

    /** Vuoto conta come acceso, come nella vecchia pagina Analitica. */
    public static function active(mixed $value): bool
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return $value === '' || $value === 'true' || $value === '1';
    }
}
