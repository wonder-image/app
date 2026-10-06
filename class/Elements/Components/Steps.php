<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

/**
 * Un percorso a passi (Carrello › Spedizione › Pagamento): i passi fatti
 * sono link, quello in corso è evidenziato, i successivi sono spenti.
 */
class Steps extends Component
{
    use Renderer;

    public const STATES = ['done', 'current', 'todo'];

    public function __construct(string $label = '')
    {
        $this->schema('label', $label)->schema('steps', []);
    }

    public static function make(string $label = ''): self
    {
        return new self($label);
    }

    public function step(string $label, ?string $href = null, string $state = 'todo'): self
    {
        $href = $href !== null && trim($href) !== '' ? $href : null;

        return $this->schemaPush('steps', [
            'label' => $label,
            'href' => $href,
            'state' => in_array($state, self::STATES, true) ? $state : 'todo',
        ]);
    }
}
