<?php

namespace Wonder\Themes\Concerns;

trait RendersButtonPostForm
{
    use RendersPostForm;

    protected function isPostButton(array $schema): bool
    {
        return ($schema['form_method'] ?? null) === 'post';
    }

    protected function openButtonPostForm(object $class, array $schema): string
    {
        $attributes = is_array($schema['form_attributes'] ?? null)
            ? $schema['form_attributes']
            : [];

        return $this->openPostForm((string) $class->getHref(), $attributes, $schema);
    }
}
