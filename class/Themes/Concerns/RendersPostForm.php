<?php

namespace Wonder\Themes\Concerns;

use Wonder\Http\Csrf;

/**
 * Un `<form method="post">` con il token CSRF e, se chiesta, la conferma
 * della lib (`data-wi-confirm*`, vedi `Elements\Concerns\HasConfirmation`).
 */
trait RendersPostForm
{
    use HasAttributes;

    /**
     * Gli attributi `data-wi-confirm*` dalle chiavi `confirm`, `confirm_title`,
     * `confirm_ok`, `confirm_variant`; vuoto senza testo.
     *
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    protected function confirmAttributes(array $source): array
    {
        $text = trim((string) ($source['confirm'] ?? ''));

        if ($text === '') {
            return [];
        }

        $attributes = ['data-wi-confirm' => $text];

        foreach (['title', 'ok', 'variant'] as $key) {
            $value = trim((string) ($source['confirm_'.$key] ?? ''));

            if ($value !== '') {
                $attributes['data-wi-confirm-'.$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $confirm
     */
    protected function openPostForm(string $action, array $attributes = [], array $confirm = []): string
    {
        $attributes['method'] = 'post';
        $attributes['action'] = trim($action);
        $attributes = array_merge($attributes, $this->confirmAttributes($confirm));
        $attributeString = $this->renderAttributes($attributes);

        return '<form'.($attributeString !== '' ? ' '.$attributeString : '').'>'.Csrf::fieldFor('post');
    }
}
