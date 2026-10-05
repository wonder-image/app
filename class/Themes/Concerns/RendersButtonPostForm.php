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

        $hidden = '';

        foreach ((array) ($schema['form_hidden'] ?? []) as $name => $value) {
            $hidden .= '<input type="hidden" name="'.htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
                .'" value="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'">';
        }

        return $this->openPostForm((string) $class->getHref(), $attributes, $schema).$hidden;
    }
}
