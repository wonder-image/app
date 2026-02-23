<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

class Alert extends Component
{
    use Renderer;

    private const ALLOWED_LEVELS = ['info', 'success', 'warning', 'error'];

    public static function make(string $message, string $level = 'info'): self
    {
        return (new self())
            ->message($message)
            ->level($level);
    }

    public function message(string $message): self
    {
        return $this->schema('message', $message);
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
    }

    public function level(string $level): self
    {
        $normalized = strtolower(trim($level));
        if (!in_array($normalized, self::ALLOWED_LEVELS, true)) {
            throw new InvalidArgumentException(
                "Livello {$level} non valido. Valori ammessi: " . implode(', ', self::ALLOWED_LEVELS)
            );
        }

        return $this->schema('level', $normalized);
    }

    public function dismissible(bool $dismissible = true): self
    {
        return $this->schema('dismissible', $dismissible);
    }
}
