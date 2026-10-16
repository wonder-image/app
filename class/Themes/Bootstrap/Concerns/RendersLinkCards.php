<?php

namespace Wonder\Themes\Bootstrap\Concerns;

use Wonder\Elements\Component;

/**
 * Parti comuni di `StatusCard`, `LinkCard` e `LinkListCard`: la card esterna
 * con le classi e gli attributi dell'Element e il riquadro dell'icona nei
 * colori `-subtle` / `-emphasis` della variante.
 */
trait RendersLinkCards
{
    use CanSpanColumn;

    private static array $cardVariants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    protected function cardVariant(array $schema): string
    {
        $variant = (string) ($schema['variant'] ?? 'primary');

        return in_array($variant, self::$cardVariants, true) ? $variant : 'primary';
    }

    /** `$size`: classi di padding e dimensione del riquadro (`p-2 fs-5`). */
    protected function cardIcon(array $schema, string $size = 'p-2 fs-5'): string
    {
        $icon = trim((string) ($schema['icon'] ?? ''));

        if (preg_match('/^bi-[a-z0-9-]+$/', $icon) !== 1) {
            return '';
        }

        $variant = $this->cardVariant($schema);

        return '<span class="d-inline-flex flex-shrink-0 rounded-3 lh-1 '.$size.' bg-'.$variant.'-subtle text-'.$variant.'-emphasis">'
            .'<i class="bi '.$icon.'" aria-hidden="true"></i>'
            .'</span>';
    }

    /** Apertura della card: classi del tema, `col-span-*` e attributi dell'Element in un solo `class`. */
    protected function cardOpen(Component $class, array $classes): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        if (property_exists($class, 'columnSpan')) {
            $classes[] = $this->getColumnSpan($class->columnSpan);
        }

        $classes = array_merge($classes, $this->classTokens($attributes['class'] ?? null));
        $attributes['class'] = implode(' ', array_values(array_unique(array_filter($classes))));

        $id = $schema['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $attributes['id'] = $id;
        }

        return '<div '.$this->renderAttributes($attributes).'>';
    }
}
