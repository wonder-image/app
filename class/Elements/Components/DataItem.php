<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/**
 * Un dato di una scheda: l'etichetta piccola sopra, il valore sotto.
 *
 *   DataItem::make('Email', $row['email'])
 *   DataItem::make('Stato', '<span class="badge text-bg-success">Attiva</span>')->html()
 *   DataItem::make('Nota interna', $nota)->action('<a href="#">Modifica</a>')
 *
 * A differenza di `InfoCard` non ha riquadro né intestazione: sta dentro a una
 * `Card` insieme ad altri, ognuno con la sua `columnSpan`. Il valore si
 * escapa; con `html()` si dichiara che è già markup fidato. Dove manca il
 * valore compare il segnaposto (un trattino).
 */
class DataItem extends Component
{
    use CanSpanColumn, Renderer;

    public function __construct(string $label = '', string|int|float|null $value = null)
    {
        $this->label($label);
        $this->value($value);
        $this->placeholder('—');
    }

    public static function make(string $label = '', string|int|float|null $value = null): static
    {
        return new static($label, $value);
    }

    public function label(string $label): static
    {
        return $this->schema('label', $label);
    }

    public function value(string|int|float|null $value): static
    {
        return $this->schema('value', $value === null ? '' : (string) $value);
    }

    /** Il valore è già HTML: non si escapa. */
    public function html(bool $html = true): static
    {
        return $this->schema('html', $html);
    }

    public function placeholder(string $placeholder = '—'): static
    {
        return $this->schema('placeholder', $placeholder);
    }

    /**
     * Markup fidato accanto all'etichetta: la matita che apre una finestra, il
     * lucchetto di un dato bloccato. Lo costruisce chi chiama, con il suo
     * escape.
     */
    public function action(string $html): static
    {
        return $this->schema('action', $html);
    }
}
