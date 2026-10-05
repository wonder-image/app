<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasConfirmation;
use Wonder\Elements\Concerns\HasPartAttributes;
use Wonder\Elements\Concerns\Renderer;

/**
 * Bottone con menu di voci.
 *
 * Opzioni di una voce (`item()`, `button()`, `action()`): `active`,
 * `disabled`, `icon`, `title`, `target`, `rel`, `blank`, `attributes`,
 * `variant` (colore della voce), `method` (`get` o `post`: `post` rende un
 * form con token CSRF, un `<button type="submit">` e l'href come action) e
 * `confirm` con `confirm_title`, `confirm_ok`, `confirm_variant` (la
 * conferma della lib, sul form per le voci POST e sul tag per le altre).
 *
 *     Dropdown::make('Azioni')
 *         ->item('Modifica', $editUrl)
 *         ->action('Copia link', ['data-copy' => $url])
 *         ->divider()
 *         ->item('Elimina', $deleteUrl, [
 *             'method' => 'post',
 *             'variant' => 'danger',
 *             'confirm' => 'Eliminare la voce?',
 *         ]);
 */
class Dropdown extends Component
{
    use CanSpanColumn, HasConfirmation, HasPartAttributes, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];
    private const ALLOWED_DIRECTIONS = ['down', 'up', 'start', 'end'];
    private const ALLOWED_ALIGNMENTS = ['start', 'end'];
    private const ALLOWED_METHODS = ['get', 'post'];

    private string $label = '';

    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    public function __construct(string $label = '')
    {
        $this->label = $label;

        $this->variant('secondary');
        $this->align('start');
        $this->direction('down');
    }

    public static function make(string $label = ''): self
    {
        return new self($label);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function items(array $items): self
    {
        $this->items = array_map(
            fn (mixed $item): mixed => is_array($item) ? $this->normalizeItem($item) : $item,
            $items
        );

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function item(string $label, ?string $href = '#', array $options = []): self
    {
        $item = array_merge($options, [
            'kind' => ($options['kind'] ?? (($href === null || $href === '') ? 'button' : 'link')),
            'label' => $label,
            'href' => $href,
        ]);

        $this->items[] = $this->normalizeItem($item);

        return $this;
    }

    /**
     * Una voce `<button type="button">` senza href, per le azioni JavaScript.
     *
     * @param array<string, mixed> $attributes attributi del bottone
     * @param array<string, mixed> $options opzioni della voce, come `item()`
     */
    public function action(string $label, array $attributes = [], array $options = []): self
    {
        $options['attributes'] = array_merge(
            is_array($options['attributes'] ?? null) ? $options['attributes'] : [],
            $attributes
        );

        return $this->button($label, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function button(string $label, array $options = []): self
    {
        $options['kind'] = 'button';

        return $this->item($label, null, $options);
    }

    public function divider(): self
    {
        $this->items[] = ['kind' => 'divider'];

        return $this;
    }

    public function header(string $label): self
    {
        $this->items[] = [
            'kind' => 'header',
            'label' => $label,
        ];

        return $this;
    }

    public function text(string $text): self
    {
        $this->items[] = [
            'kind' => 'text',
            'label' => $text,
        ];

        return $this;
    }

    /** Classi aggiunte al bottone che apre il menu. */
    public function toggleClass(string $class): self
    {
        return $this->setPartClass('toggle', $class);
    }

    /** Classi aggiunte al contenitore delle voci. */
    public function menuClass(string $class): self
    {
        return $this->setPartClass('menu', $class);
    }

    /** Classi aggiunte a ogni voce cliccabile (link, bottone, POST). */
    public function itemClass(string $class): self
    {
        return $this->setPartClass('item', $class);
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', $variant !== '' ? $variant : 'secondary');
    }

    public function outline(bool $outline = true): self
    {
        return $this->schema('outline', $outline);
    }

    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));
        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione dropdown non valida. Valori ammessi: '.implode(', ', array_filter(self::ALLOWED_SIZES))
            );
        }

        return $this->schema('size', $normalized);
    }

    public function direction(string $direction): self
    {
        $normalized = strtolower(trim($direction));
        if (!in_array($normalized, self::ALLOWED_DIRECTIONS, true)) {
            throw new InvalidArgumentException(
                'Direzione dropdown non valida. Valori ammessi: '.implode(', ', self::ALLOWED_DIRECTIONS)
            );
        }

        return $this->schema('direction', $normalized);
    }

    public function align(string $align): self
    {
        $normalized = strtolower(trim($align));
        if (!in_array($normalized, self::ALLOWED_ALIGNMENTS, true)) {
            throw new InvalidArgumentException(
                'Allineamento dropdown non valido. Valori ammessi: '.implode(', ', self::ALLOWED_ALIGNMENTS)
            );
        }

        return $this->schema('align', $normalized);
    }

    public function dark(bool $dark = true): self
    {
        return $this->schema('dark', $dark);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }

    public function grouped(bool $grouped = true): self
    {
        return $this->schema('grouped', $grouped);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        if (array_key_exists('method', $item)) {
            $method = strtolower(trim((string) $item['method']));
            unset($item['method']);

            if (!in_array($method, self::ALLOWED_METHODS, true)) {
                throw new InvalidArgumentException(
                    'Metodo della voce non valido. Valori ammessi: '.implode(', ', self::ALLOWED_METHODS)
                );
            }

            if ($method === 'post') {
                if (trim((string) ($item['href'] ?? '')) === '') {
                    throw new InvalidArgumentException('Una voce POST richiede un href, usato come action del form.');
                }

                $item['kind'] = 'post';
            }
        }

        if (array_key_exists('variant', $item)) {
            $variant = strtolower(trim((string) $item['variant']));

            if ($variant !== '' && !preg_match('/^[a-z][a-z0-9-]*$/', $variant)) {
                throw new InvalidArgumentException('Variante della voce non valida: lettere, numeri e trattini.');
            }

            $item['variant'] = $variant;
        }

        if (array_key_exists('confirm', $item)) {
            $confirm = $this->confirmationSchema(
                (string) $item['confirm'],
                isset($item['confirm_title']) ? (string) $item['confirm_title'] : null,
                isset($item['confirm_ok']) ? (string) $item['confirm_ok'] : null,
                isset($item['confirm_variant']) ? (string) $item['confirm_variant'] : null
            );

            unset($item['confirm'], $item['confirm_title'], $item['confirm_ok'], $item['confirm_variant']);
            $item = array_merge($item, $confirm);
        }

        return $item;
    }
}
