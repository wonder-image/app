<?php

namespace Wonder\Backend\Support;

/**
 * Normalizza i descriptor dei bottoni header (`$ACTIONS`) usati da
 * `app/view/layout/backend/show.php`.
 *
 * Ogni descriptor valido diventa un array con chiavi stabili. Sono
 * supportati due tipi:
 *
 *  - **bottone piatto** — `label` (+ `href`/`onclick`, opzionali `class`,
 *    `icon`, `target`). Chiave `items` sempre presente e vuota.
 *  - **bottone con dropdown** — un descriptor con chiave `items` non vuota.
 *    Il toggle usa `label`/`icon`/`class` (variante del bottone) e `align`
 *    (`start`|`end`, default `end`). Ogni voce del menu è normalizzata a
 *    uno di questi `kind`:
 *      - `divider`  — `['divider' => true]` oppure la stringa `'divider'`
 *      - `header`   — `['header' => 'Testo']`
 *      - `item`     — voce cliccabile (link se `href`, bottone se solo
 *                     `onclick`) con `label`, `href`, `icon`, `target`,
 *                     `onclick`, `class` (extra su `.dropdown-item`),
 *                     `disabled`, `active`.
 *
 * I descriptor mal formati (non-array, senza `label`, dropdown senza voci
 * valide) vengono scartati silenziosamente: un bottone "vuoto" non deve
 * abbattere la pagina.
 */
final class PageActionNormalizer
{
    /**
     * @param array<int, mixed> $descriptors
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $descriptors): array
    {
        $normalized = [];

        foreach ($descriptors as $descriptor) {
            if (!is_array($descriptor)) {
                continue;
            }

            $entry = self::normalizeDescriptor($descriptor);

            if ($entry !== null) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $descriptor
     * @return array<string, mixed>|null
     */
    private static function normalizeDescriptor(array $descriptor): ?array
    {
        $label = trim((string) ($descriptor['label'] ?? ''));

        if ($label === '') {
            return null;
        }

        $items = self::normalizeItems($descriptor['items'] ?? null);

        if ($items !== []) {
            return [
                'label' => $label,
                'class' => trim((string) ($descriptor['class'] ?? 'btn-primary')),
                'icon' => trim((string) ($descriptor['icon'] ?? '')),
                'align' => self::normalizeAlign($descriptor['align'] ?? null),
                'items' => $items,
            ];
        }

        return [
            'label' => $label,
            'href' => (string) ($descriptor['href'] ?? ''),
            'class' => trim((string) ($descriptor['class'] ?? 'btn-primary')),
            'icon' => trim((string) ($descriptor['icon'] ?? '')),
            'target' => trim((string) ($descriptor['target'] ?? '')),
            'onclick' => trim((string) ($descriptor['onclick'] ?? '')),
            'items' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if ($item === 'divider') {
                $normalized[] = ['kind' => 'divider'];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['divider'])) {
                $normalized[] = ['kind' => 'divider'];
                continue;
            }

            $header = trim((string) ($item['header'] ?? ''));

            if ($header !== '') {
                $normalized[] = ['kind' => 'header', 'label' => $header];
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'kind' => 'item',
                'label' => $label,
                'href' => (string) ($item['href'] ?? ''),
                'icon' => trim((string) ($item['icon'] ?? '')),
                'target' => trim((string) ($item['target'] ?? '')),
                'onclick' => trim((string) ($item['onclick'] ?? '')),
                'class' => trim((string) ($item['class'] ?? '')),
                'disabled' => (bool) ($item['disabled'] ?? false),
                'active' => (bool) ($item['active'] ?? false),
            ];
        }

        return $normalized;
    }

    private static function normalizeAlign(mixed $align): string
    {
        return trim((string) $align) === 'start' ? 'start' : 'end';
    }
}
