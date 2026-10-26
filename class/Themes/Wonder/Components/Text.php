<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Elements\Components\Link;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ColumnSpanClasses;

/**
 * Text e RichText (che lo estende) sulle utility tipografiche della lib.
 * `italic()` non ha una utility nella lib: nel tema Wonder non aggiunge classi.
 */
class Text extends Component
{
    use ColumnSpanClasses, RendersComponentAttributes;

    private const ALLOWED_TAGS = ['p', 'span', 'div', 'small', 'strong', 'em', 'mark', 'abbr', 'blockquote'];

    private const ALIGN = [
        'start' => 'a-l',
        'left' => 'a-l',
        'center' => 'a-c',
        'end' => 'a-r',
        'right' => 'a-r',
    ];

    public function render($class): string
    {
        $schema = $class->getSchema();
        $tag = (string) ($schema['tag'] ?? 'p');

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $tag = 'p';
        }

        $attributes = $this->renderComponentAttributes($class, $this->textClasses($schema));
        $body = $this->renderBody($class, (array) ($schema['parts'] ?? []), (bool) ($schema['html'] ?? false));
        $html = "<{$tag}".($attributes !== '' ? ' '.$attributes : '').">{$body}</{$tag}>";

        return $this->wrapColumnSpan($class, $html);
    }

    /** @return string[] */
    protected function textClasses(array $schema): array
    {
        $classes = [];

        if (($schema['lead'] ?? false) === true) {
            $classes[] = 'subtitle';
        }

        if (($schema['muted'] ?? false) === true) {
            $classes[] = 'tx-secondary';
        }

        if (($schema['small'] ?? false) === true) {
            $classes[] = 'text-small';
        }

        if (($schema['bold'] ?? false) === true) {
            $classes[] = 'fw-700';
        }

        $color = trim((string) ($schema['color'] ?? ''));
        if ($color !== '') {
            $classes[] = 'tx-'.$color;
        }

        $align = self::ALIGN[strtolower(trim((string) ($schema['align'] ?? '')))] ?? null;
        if ($align !== null) {
            $classes[] = $align;
        }

        return $classes;
    }

    /** @param array<int, string|Link> $parts */
    private function renderBody(object $class, array $parts, bool $rawHtml): string
    {
        if ($parts === []) {
            $legacy = method_exists($class, 'getText') ? (string) $class->getText() : '';

            return $rawHtml ? $legacy : $this->escape($legacy);
        }

        $output = '';

        foreach ($parts as $part) {
            if (is_string($part)) {
                $output .= $rawHtml ? $part : $this->escape($part);
            } elseif ($part instanceof Link) {
                $output .= $this->renderInlineLink($part);
            }
        }

        return $output;
    }

    /** `Link` non ha un renderer Wonder: dentro al testo è un `<a>` semplice. */
    protected function renderInlineLink(Link $link): string
    {
        $schema = $link->getSchema();
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $classes = $this->classTokens($attributes['class'] ?? null);

        if (($schema['muted'] ?? false) === true) {
            $classes[] = 'tx-secondary';
        }

        if ($classes !== []) {
            $attributes['class'] = implode(' ', array_values(array_unique($classes)));
        } else {
            unset($attributes['class']);
        }

        $attributeString = $this->renderAttributes($attributes);
        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'" aria-hidden="true"></i>' : '';
        $label = $this->escape($link->getLabel());

        $inner = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $label.' '.$iconHtml,
            'start' => $iconHtml.' '.$label,
            default => $label,
        };

        return '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$inner.'</a>';
    }
}
