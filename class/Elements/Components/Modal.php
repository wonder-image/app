<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\IsContainer;

/**
 * Una finestra Bootstrap scritta nel layout come un Accordion: titolo, corpo
 * a griglia con i suoi campi, bottoni in fondo.
 *
 * ```php
 * Modal::make('Costo del fornitore')
 *     ->id('wi-cost-modal')
 *     ->columns(12)
 *     ->components([
 *         FormField::key('wi_cost_code')->text()->label('Codice fornitore')->columnSpan(6),
 *         FormField::key('wi_cost_price')->price()->label('Costo')->columnSpan(6),
 *     ])
 *     ->footer([
 *         Button::make('Annulla')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
 *         Button::make('Salva')->attr('data-wi-cost-save', 'true'),
 *     ])
 * ```
 *
 * Dentro non c'è un `<form>` e la finestra esce dal form della Resource
 * appena la pagina è pronta: i suoi campi li legge e li scrive uno script
 * della pagina, non partono con il record. La apre un bottone con
 * `opensModal()` (o `data-bs-toggle="modal"`). Vive solo nel backend
 * Bootstrap: sul tema Wonder non si disegna.
 */
class Modal extends Component
{
    use IsContainer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg', 'xl'];

    /** I bottoni in fondo, di solito `Components\Button`. */
    public array $footer = [];

    public function __construct(string $title = '')
    {
        $this->schema('title', $title);
        $this->columnSpan(12);
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
    }

    public function getTitle(): string
    {
        return (string) ($this->getSchema('title') ?? '');
    }

    /** `sm`, `lg`, `xl` o stringa vuota per la misura normale. */
    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));

        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione della finestra non valida. Valori ammessi: sm, lg, xl'
            );
        }

        return $this->schema('size', $normalized);
    }

    /** Il corpo scorre e l'intestazione con i bottoni resta ferma. */
    public function scrollable(bool $scrollable = true): self
    {
        return $this->schema('scrollable', $scrollable);
    }

    /** @param array<int, Component> $components */
    public function footer(array $components): self
    {
        $this->footer = array_values($components);

        return $this;
    }
}
