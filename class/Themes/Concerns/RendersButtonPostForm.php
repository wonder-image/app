<?php

namespace Wonder\Themes\Concerns;

trait RendersButtonPostForm
{
    protected function isPostButton(array $schema): bool
    {
        return ($schema['form_method'] ?? null) === 'post';
    }

    protected function openButtonPostForm(object $class, array $schema): string
    {
        $attributes = is_array($schema['form_attributes'] ?? null)
            ? $schema['form_attributes']
            : [];
        $attributes['method'] = 'post';
        $attributes['action'] = trim((string) $class->getHref());

        $confirm = trim((string) ($schema['confirm'] ?? ''));
        if ($confirm !== '') {
            $encoded = json_encode(
                $confirm,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?: '""';
            $attributes['onsubmit'] = 'return window.confirm('.$encoded.');';
        }

        $attributeString = $this->renderAttributes($attributes);

        return '<form'.($attributeString !== '' ? ' '.$attributeString : '').'>';
    }
}
