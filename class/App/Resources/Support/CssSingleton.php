<?php

namespace Wonder\App\Resources\Support;

use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Support\TableSync;

abstract class CssSingleton extends SingletonResource
{
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('css', 'Stile', 'bi-award', 1010, ['admin'])
            ->authority(['admin']);
    }

    /**
     * Valori di default mostrati nel form backend quando il record
     * singleton è assente o ha campi vuoti.
     *
     * Le pagine CSS condividono i default runtime con la generazione
     * del `root.css` (vedi `RuntimeDefaults::css*Row()` usati in
     * `app/function/other/css.php`). Senza questo merge, il form di
     * modifica mostrerebbe campi vuoti finché il record non viene
     * salvato almeno una volta — anche se il CSS generato usa già i
     * default. Ogni resource CSS ritorna qui la propria riga di
     * default.
     *
     * @return array<string, mixed>
     */
    protected static function formDefaults(): array
    {
        return [];
    }

    /**
     * Riempie i valori del form con i default (solo per i campi
     * assenti o vuoti), così la pagina di modifica rispecchia i
     * default runtime invece di apparire vuota.
     */
    public static function mutateFormValues(
        array $values,
        string $mode,
        string $context = 'backend'
    ): array {
        foreach (static::formDefaults() as $key => $default) {
            $current = $values[$key] ?? null;

            if ($current === null || $current === '') {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        static::refreshCss();
    }

    protected static function refreshCss(): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }

        TableSync::autoExport();
    }
}
