<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

class Button extends Link
{
    use CanSpanColumn, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];
    private const ALLOWED_TYPES = ['a', 'button', 'submit', 'reset', 'post'];

    public function __construct(string $label = '', string $href = '')
    {
        parent::__construct($href, $label);

        $this->variant('primary');
        $this->type('button');
    }

    public static function make(string $label = '', string $href = ''): self
    {
        return new self($label, $href);
    }

    public static function to(string $href, string $label): self
    {
        return new self($label, $href);
    }

    public static function post(string $action, string $label): self
    {
        return (new self($label, $action))->type('post');
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', $variant !== '' ? $variant : 'primary');
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
                'Dimensione bottone non valida. Valori ammessi: '.implode(', ', array_filter(self::ALLOWED_SIZES))
            );
        }

        return $this->schema('size', $normalized);
    }

    public function type(string $type): self
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                'Tipo bottone non valido. Valori ammessi: '.implode(', ', self::ALLOWED_TYPES)
            );
        }

        if ($normalized === 'post') {
            return $this
                ->schema('form_method', 'post')
                ->schema('type', 'submit');
        }

        unset($this->schema['form_method']);

        return $this->schema('type', $normalized);
    }

    public function confirm(string $message): self
    {
        $message = trim($message);

        if ($message === '') {
            unset($this->schema['confirm']);

            return $this;
        }

        return $this->schema('confirm', $message);
    }

    /** Open one URL or a gallery. Extensionless image endpoints can use type: 'image'. */
    public function lightbox(string|array $urls, ?string $type = null): self
    {
        if ($type !== null && !in_array($type, ['image', 'iframe'], true)) {
            throw new InvalidArgumentException('Lightbox type must be image or iframe.');
        }
        $items = [];
        foreach (is_array($urls) ? $urls : [$urls] as $url) {
            if (!is_string($url) || trim($url) === '') {
                throw new InvalidArgumentException('Lightbox URLs must be non-empty strings.');
            }
            $url = trim($url);
            if (preg_match('/[\x00-\x20\x7f]/', $url)
                || (parse_url($url, PHP_URL_SCHEME) !== null
                    && !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new InvalidArgumentException('Lightbox URLs must use HTTP(S) or relative paths.');
            }
            $path = (string) parse_url($url, PHP_URL_PATH);
            $items[] = ['src' => $url, 'type' => $type
                ?? (preg_match('/\.(?:avif|webp|png|jpe?g|gif|svg|bmp|ico)$/i', $path) ? 'image' : 'iframe')];
        }
        return $this->schema('lightbox', $items);
    }

    public function formAttributes(array $attributes): self
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            if (is_string($key) && trim($key) !== '') {
                $normalized[trim($key)] = $value;
            }
        }

        return $this->schema('form_attributes', $normalized);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }

    public function active(bool $active = true): self
    {
        return $this->schema('active', $active);
    }

    public function block(bool $block = true): self
    {
        return $this->schema('block', $block);
    }

    public function nowrap(bool $nowrap = true): self
    {
        return $this->schema('nowrap', $nowrap);
    }

    public function arrow(bool $arrow = true): self
    {
        if ($arrow && trim((string) ($this->schema['icon'] ?? '')) === '') {
            $this->icon('bi bi-chevron-right', 'end');
        }

        return $this->schema('arrow', $arrow);
    }
}
