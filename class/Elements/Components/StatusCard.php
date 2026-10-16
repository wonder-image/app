<?php

namespace Wonder\Elements\Components;

/**
 * Card di stato: icona colorata, titolo, valore e una riga di dettaglio;
 * con `href()` tutta la card porta alla pagina indicata.
 */
class StatusCard extends AbstractValueCard
{
    public function __construct(
        string $title = '',
        string|int|float|bool|null $value = null
    ) {
        parent::__construct($title, $value);

        $this->valueLevel(5);
        $this->variant('primary');
    }

    /** Classe Bootstrap Icons (`bi-clock-history`). */
    public function icon(string $icon): static
    {
        return $this->schema('icon', $icon);
    }

    /** `primary`, `secondary`, `success`, `danger`, `warning`, `info`, `light`, `dark`. */
    public function variant(string $variant): static
    {
        return $this->schema('variant', $variant);
    }

    public function detail(string $detail): static
    {
        return $this->schema('detail', $detail);
    }

    public function href(string $href): static
    {
        return $this->schema('href', $href);
    }
}
