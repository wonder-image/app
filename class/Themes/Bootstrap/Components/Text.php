<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\Link;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\HasAttributes;

class Text extends Component
{
    use CanSpanColumn, RendersText, HasAttributes;

    private const ALLOWED_TAGS = ['p', 'span', 'div', 'small', 'strong', 'em', 'mark', 'abbr', 'blockquote'];

    public function render($class): string
    {
        $schema = $class->getSchema();
        $tag = (string) ($schema['tag'] ?? 'p');
        $classes = $this->textClasses($schema);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);
        $body = $this->renderBody($class, (array) ($schema['parts'] ?? []), (bool) ($schema['html'] ?? false));

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $tag = 'p';
        }

        if (($schema['lead'] ?? false) === true) {
            $classes[] = 'lead';
        }

        $classAttr = $this->buildClassAttribute($classes);

        return "<div class=\"{$classSpanColumn}\">"
            ."<{$tag}{$classAttr} {$attributes}>{$body}</{$tag}>"
            .'</div>';
    }

    /**
     * Compone i frammenti `parts`: stringa → escape (o raw se `html`),
     * `Link` → render Bootstrap del link inline. Se `parts` è vuoto
     * (es. Text vuoto o solo `getText()` storico) cade su `$class->getText()`
     * per back-compat.
     *
     * @param array<int, string|Link> $parts
     */
    private function renderBody(object $class, array $parts, bool $rawHtml): string
    {
        if ($parts === []) {
            $legacy = method_exists($class, 'getText') ? (string) $class->getText() : '';

            return $rawHtml ? $legacy : $this->escapeText($legacy);
        }

        $output = '';

        foreach ($parts as $part) {

            if (is_string($part)) {
                $output .= $rawHtml ? $part : $this->escapeText($part);
                continue;
            }

            if ($part instanceof Link) {
                $output .= $this->renderInlineLink($part);
            }

        }

        return $output;
    }

    /**
     * Inline rendering di un `Link`: stessa struttura del `Link` Component
     * standalone ma senza il `<div>` di column-span esterno. Vivere come
     * `<a>` puro dentro al testo.
     */
    private function renderInlineLink(Link $link): string
    {
        $schema = $link->getSchema();
        $href = $link->getHref();
        $label = $link->getLabel();
        $target = trim((string) ($schema['target'] ?? ''));
        $rel = trim((string) ($schema['rel'] ?? ''));
        $title = trim((string) ($schema['title'] ?? ''));
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $muted = (bool) ($schema['muted'] ?? false);
        $rawClass = (string) (($schema['attributes']['class'] ?? '') ?: '');

        $classes = array_filter(array_map('trim', explode(' ', $rawClass)));
        if ($muted) {
            $classes[] = 'text-body-secondary';
        }

        $extras = [];
        if ($target !== '') {
            $extras[] = 'target="'.$this->escape($target).'"';
        }
        if ($rel !== '') {
            $extras[] = 'rel="'.$this->escape($rel).'"';
        }
        if ($title !== '') {
            $extras[] = 'title="'.$this->escape($title).'"';
        }

        $classAttr = $classes === [] ? '' : ' class="'.$this->escape(implode(' ', $classes)).'"';
        $extraAttr = $extras === [] ? '' : ' '.implode(' ', $extras);

        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $labelHtml = $this->escape($label);

        $inner = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $labelHtml.' '.$iconHtml,
            'start' => $iconHtml.' '.$labelHtml,
            default => $labelHtml,
        };

        return '<a href="'.$this->escape($href).'"'.$classAttr.$extraAttr.'>'.$inner.'</a>';
    }
}
