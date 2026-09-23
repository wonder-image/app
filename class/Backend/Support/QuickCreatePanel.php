<?php

namespace Wonder\Backend\Support;

use Closure;
use Wonder\Elements\Components\Container;

/**
 * Risolve i campi e il corpo del modal di creazione rapida a partire dalla
 * config dichiarata con `->quickCreate(...)`. Tiene la logica fuori dal
 * renderer del tema. Vedi docs/app/concetti/form/quick-create.md.
 */
final class QuickCreatePanel
{
    /** Chiavi dei campi obbligatori nel `formSchema()` del target. */
    public static function requiredFields(string $resourceClass): array
    {
        $keys = [];

        foreach ((array) $resourceClass::formSchema() as $field) {
            if (!is_object($field) || !isset($field->name) || $field->name === '') {
                continue;
            }

            $attributes = ' '.trim((string) $field->get('attribute')).' ';

            if (str_contains($attributes, ' required ')) {
                $keys[] = $field->name;
            }
        }

        return $keys;
    }

    /** Campi da mostrare: quelli dichiarati, altrimenti gli obbligatori del target. */
    public static function fields(array $config): array
    {
        $fields = $config['fields'] ?? null;

        if (is_array($fields) && $fields !== []) {
            return array_values(array_filter($fields, 'is_string'));
        }

        return self::requiredFields((string) $config['resource']);
    }

    /**
     * Testo del bottone "Aggiungi …": quello dichiarato con `button:`,
     * altrimenti il nome leggibile della risorsa target (`label()`, la stessa
     * fonte di `defaultPageTitles()['create']`), con ripiego sullo slug quando
     * la risorsa non lo espone.
     */
    public static function buttonLabel(array $config): string
    {
        $declared = trim((string) ($config['button'] ?? ''));

        if ($declared !== '') {
            return $declared;
        }

        $resource = (string) ($config['resource'] ?? '');
        $name = '';

        if ($resource !== '' && method_exists($resource, 'label')) {
            $name = trim((string) $resource::label());
        }

        if ($name === '') {
            $name = trim((string) ($config['slug'] ?? ''));
        }

        return $name === '' ? 'Aggiungi' : 'Aggiungi '.$name;
    }

    /** Campo etichetta dell'opzione: dichiarato, altrimenti name/title/primo campo. */
    public static function label(array $config, array $fields): string
    {
        $label = trim((string) ($config['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        foreach (['name', 'title', 'label'] as $candidate) {
            if (in_array($candidate, $fields, true)) {
                return $candidate;
            }
        }

        return $fields[0] ?? 'name';
    }

    /**
     * HTML del corpo del modal: il layout custom se dichiarato, altrimenti i
     * campi (obbligatori o subset) avvolti in un `Container` — resi con lo
     * stesso renderer dei form backend.
     */
    public static function bodyHtml(array $config, array $fields): string
    {
        $layout = $config['layout'] ?? null;

        if ($layout instanceof Closure) {
            $built = $layout();

            if ($built !== null) {
                return ResourceFormLayoutRenderer::renderLayout($built);
            }
        }

        $resource = (string) $config['resource'];
        $components = [];

        foreach ($fields as $key) {
            $components[] = $resource::getInput($key);
        }

        return ResourceFormLayoutRenderer::renderLayout((new Container())->components($components));
    }
}
