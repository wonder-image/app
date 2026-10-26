<?php

namespace Wonder\Elements\Frontend;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\HasPartAttributes;
use Wonder\Elements\Concerns\Renderer;

/**
 * Lo stato vuoto del frontend: icona, titolo, testo e un'azione opzionale,
 * sulle classi `wi-empty-state*` della lib.
 *
 *   EmptyState::make('Non hai ancora ordini.', 'bi bi-bag')
 *   EmptyState::make('Il carrello è vuoto.', 'bi bi-cart')
 *       ->title('Niente qui')->titleTag('h3')->textClass('text tx-secondary')
 *       ->action(Button::make('Vai al negozio')->href('/shop/'))
 *       ->attr('role', 'status')
 */
class EmptyState extends Component
{
    use HasPartAttributes, Renderer;

    public const TITLE_TAGS = ['h2', 'h3', 'h4', 'h5', 'h6'];

    public function __construct(string $text = '', string $icon = '')
    {
        $this->schema('text', $text);
        $this->schema('icon', trim($icon));
    }

    public static function make(string $text = '', string $icon = ''): static
    {
        return new static($text, $icon);
    }

    /** Le classi dell'icona, per esempio `bi bi-geo-alt`; vuota, l'icona non c'è. */
    public function icon(string $icon): static
    {
        return $this->schema('icon', trim($icon));
    }

    public function title(string $title): static
    {
        return $this->schema('title', $title);
    }

    /** Il livello del titolo, da `h2` (default) a `h6`, per seguire i titoli della sezione. */
    public function titleTag(string $tag): static
    {
        $tag = strtolower(trim($tag));

        if (!in_array($tag, self::TITLE_TAGS, true)) {
            throw new InvalidArgumentException("Il titolo di EmptyState va da h2 a h6, non «{$tag}».");
        }

        return $this->schema('title_tag', $tag);
    }

    public function text(string $text): static
    {
        return $this->schema('text', $text);
    }

    /** Un componente renderizzabile sotto il testo, di solito un `Button`. */
    public function action(object $action): static
    {
        if (!method_exists($action, 'render')) {
            throw new InvalidArgumentException("L'azione di EmptyState deve essere un oggetto renderizzabile.");
        }

        return $this->schema('action', $action);
    }

    public function iconClass(string $class): static
    {
        return $this->setPartClass('icon', $class);
    }

    public function titleClass(string $class): static
    {
        return $this->setPartClass('title', $class);
    }

    public function textClass(string $class): static
    {
        return $this->setPartClass('text', $class);
    }
}
