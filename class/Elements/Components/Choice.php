<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

/**
 * Un radio o un checkbox dentro un riquadro cliccabile: titolo, testo
 * sotto e una colonna a destra per il prezzo.
 */
class Choice extends Component
{
    use Renderer;

    public function __construct(string $name, string|int $value)
    {
        $this->schema('name', $name)
            ->schema('value', (string) $value)
            ->schema('type', 'radio')
            ->schema('title', '')
            ->schema('text', '')
            ->schema('aside', '')
            ->schema('checked', false)
            ->schema('disabled', false)
            ->schema('icon', '')
            ->schema('icons', [])
            ->schema('icons_max', 3)
            ->schema('panel', '');
    }

    public static function make(string $name, string|int $value): self
    {
        return new self($name, $value);
    }

    public function type(string $type): self
    {
        return $this->schema('type', $type === 'checkbox' ? 'checkbox' : 'radio');
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
    }

    public function text(string $text): self
    {
        return $this->schema('text', $text);
    }

    public function aside(string $aside): self
    {
        return $this->schema('aside', $aside);
    }

    public function checked(bool $checked = true): self
    {
        return $this->schema('checked', $checked);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }

    /**
     * Icona di Bootstrap Icons prima del titolo, per i segmenti: `truck`
     * oppure `bi-truck`. Restano solo lettere minuscole, cifre e trattini.
     */
    public function icon(string $icon): self
    {
        $icon = (string) preg_replace('/[^a-z0-9-]/', '', strtolower(trim($icon)));

        return $this->schema('icon', (string) preg_replace('/^bi-/', '', $icon));
    }

    /**
     * Loghi a destra del titolo (carte, wallet): ogni voce è
     * `['src' => …, 'alt' => …]`, le voci senza `src` si saltano. Oltre
     * `$max` loghi resta un «+N».
     *
     * @param array<int, array{src?: string, alt?: string}> $icons
     */
    public function icons(array $icons, int $max = 3): self
    {
        $list = [];

        foreach ($icons as $icon) {
            $src = is_array($icon) ? trim((string) ($icon['src'] ?? '')) : '';

            if ($src !== '') {
                $list[] = ['src' => $src, 'alt' => trim((string) ($icon['alt'] ?? ''))];
            }
        }

        return $this->schema('icons', $list)->schema('icons_max', max(1, $max));
    }

    /**
     * Testo di un riquadro sotto il Choice, visibile solo quando l'input è
     * scelto (istruzioni del bonifico, «verrai reindirizzato a…»).
     */
    public function panel(string $text): self
    {
        return $this->schema('panel', $text);
    }
}
