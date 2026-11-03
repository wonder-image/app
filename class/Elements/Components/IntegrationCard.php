<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/**
 * La card di un servizio esterno: icona, nome, descrizione, lo stato in alto
 * a destra e badge e avvisi in fondo. Tutta la card apre il modal indicato.
 *
 * ```php
 * IntegrationCard::make('Stripe', 'integration-stripe')
 *     ->icon('bi-stripe')
 *     ->description('Pagamenti con carta.')
 *     ->status('Connessa', 'success')
 *     ->badge('Test', 'warning')
 *     ->notice('Richiede Google Cloud Platform');
 * ```
 */
class IntegrationCard extends Component
{
    use CanSpanColumn, Renderer;

    public function __construct(string $title = '', string $modal = '')
    {
        $this->schema('title', $title);
        $this->schema('badges', []);
        $this->schema('notices', []);
        $this->variant('primary');

        if ($modal !== '') {
            $this->modal($modal);
        }
    }

    public static function make(string $title, string $modal): static
    {
        return new static($title, $modal);
    }

    /** L'id del `Modal` che la card apre. */
    public function modal(string $id): static
    {
        $id = ltrim(trim($id), '#');

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException('Modal id must be selector-safe.');
        }

        return $this->schema('modal', $id);
    }

    /** Classe Bootstrap Icons (`bi-stripe`). */
    public function icon(string $icon): static
    {
        return $this->schema('icon', $icon);
    }

    public function variant(string $variant): static
    {
        return $this->schema('variant', $variant);
    }

    public function description(string $description): static
    {
        return $this->schema('description', $description);
    }

    /** Lo stato in alto a destra, con la variante del suo colore. */
    public function status(string $label, string $variant = 'secondary'): static
    {
        return $this->schema('status', ['label' => $label, 'variant' => $variant]);
    }

    /** Un badge in fondo alla card; si sommano. */
    public function badge(string $label, string $variant = 'secondary', ?string $icon = null): static
    {
        $badges = $this->schema['badges'];
        $badges[] = ['label' => $label, 'variant' => $variant, 'icon' => $icon];

        return $this->schema('badges', $badges);
    }

    /** Una riga d'avviso in fondo alla card, per esempio una dipendenza mancante; si sommano. */
    public function notice(string $text, string $icon = 'bi-exclamation-triangle'): static
    {
        $notices = $this->schema['notices'];
        $notices[] = ['text' => $text, 'icon' => $icon];

        return $this->schema('notices', $notices);
    }
}
