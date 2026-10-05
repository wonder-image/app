<?php

namespace Wonder\Themes\Concerns;

/**
 * Le voci cliccabili del Dropdown (link, bottone, POST), uguali nei temi.
 *
 * Il tema passa le sue classi (`dropdown-item`, `wi-dropdown-item`) e il
 * prefisso del colore (`text-`, `tx-`); qui si aggiungono `active`,
 * `disabled`, il colore della voce, le classi di `itemClass()` e quelle
 * passate negli `attributes` della voce, tutte in un solo attributo `class`.
 * La conferma va sul form per le voci POST, sul tag per le altre.
 */
trait RendersDropdownItems
{
    use RendersPartAttributes, RendersPostForm;

    /** @param array<string, mixed> $item */
    protected function renderDropdownAction(object $dropdown, array $item, string $themeClasses, string $colorPrefix, string $label): string
    {
        $kind = (string) ($item['kind'] ?? 'link');
        $disabled = !empty($item['disabled']);
        $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
        $classes = [$themeClasses];

        if (!empty($item['active'])) {
            $classes[] = 'active';
        }
        if ($disabled) {
            $classes[] = 'disabled';
        }
        if (($item['variant'] ?? '') !== '') {
            $classes[] = $colorPrefix.$item['variant'];
        }

        $class = $this->mergeClassAttribute(
            $this->partClass($dropdown, 'item', implode(' ', $classes)),
            $attributes['class'] ?? []
        );
        unset($attributes['class']);

        if ($kind === 'post') {
            return $this->openPostForm((string) ($item['href'] ?? ''), [], $item)
                .'<button type="submit" class="'.$this->escape($class).'"'
                .($disabled ? ' disabled' : '')
                .$this->dropdownItemAttributes($attributes, $disabled ? ['type', 'disabled'] : ['type'])
                .'>'.$label.'</button></form>';
        }

        $attributes = array_merge($attributes, $this->confirmAttributes($item));

        if ($kind === 'button') {
            return '<button type="button" class="'.$this->escape($class).'"'
                .($disabled ? ' disabled' : '')
                .$this->dropdownItemAttributes($attributes, $disabled ? ['type', 'disabled'] : ['type'])
                .'>'.$label.'</button>';
        }

        $extra = '';
        $written = ['href'];
        $target = trim((string) ($item['target'] ?? ''));
        $rel = trim((string) ($item['rel'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));

        if (!empty($item['blank'])) {
            $target = '_blank';
            $rel = trim($rel.' noopener noreferrer');
        }

        foreach (['target' => $target, 'rel' => $rel, 'title' => $title] as $key => $value) {
            if ($value !== '') {
                $extra .= ' '.$key.'="'.$this->escape($value).'"';
                $written[] = $key;
            }
        }

        return '<a href="'.$this->escape(trim((string) ($item['href'] ?? '#'))).'" class="'.$this->escape($class).'"'
            .$extra
            .$this->dropdownItemAttributes($attributes, $written)
            .'>'.$label.'</a>';
    }

    /**
     * @param array<string, mixed> $attributes
     * @param string[] $written
     */
    private function dropdownItemAttributes(array $attributes, array $written): string
    {
        foreach ($written as $key) {
            unset($attributes[$key]);
        }

        $html = $this->renderAttributes($attributes);

        return $html !== '' ? ' '.$html : '';
    }
}
