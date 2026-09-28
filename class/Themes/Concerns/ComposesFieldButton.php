<?php

namespace Wonder\Themes\Concerns;

use Wonder\Elements\Components\Button;

/**
 * Il bottone fra i campi (`Elements\Form\Components\Button`) composto con il
 * `Components\Button` del tema, e la didascalia che gli sta accanto.
 *
 * Condiviso dai renderer Bootstrap e Wonder: cambia solo il markup intorno.
 */
trait ComposesFieldButton
{
    /**
     * Il `Components\Button` con testo, variante e attributi del campo.
     *
     * Restano fuori `id` e `name`: l'id è casuale e nelle righe clonate di un
     * repeater sarebbe lo stesso per tutte, il nome posterebbe il bottone.
     *
     * @param array<string, mixed> $schema
     */
    protected function fieldButton(array $schema): Button
    {
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $disabled = !empty($attributes['disabled']);
        unset($attributes['id'], $attributes['name'], $attributes['value'], $attributes['data-wi-check'], $attributes['disabled']);

        $button = Button::make((string) ($schema['label'] ?? ''))
            ->variant((string) ($schema['variant'] ?? 'secondary'))
            ->outline((bool) ($schema['outline'] ?? true))
            ->disabled($disabled)
            ->attributes($attributes)
            ->schema('inline', true);

        $icon = trim((string) ($schema['icon'] ?? ''));
        if ($icon !== '') {
            $button->icon($icon, 'start');
        }

        $size = trim((string) ($schema['size'] ?? ''));
        if ($size !== '') {
            $button->size($size);
        }

        return $button;
    }

    /**
     * Il value come testo, o la didascalia di riserva quando è vuoto.
     *
     * @param array<string, mixed> $schema
     */
    protected function fieldButtonCaption(array $schema): string
    {
        $value = $schema['value'] ?? null;
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text !== '' ? $text : (string) ($schema['empty_caption'] ?? '');
    }
}
