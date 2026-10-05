<?php

namespace Wonder\Themes\Concerns;

trait RendersButtonModal
{
    private function modalAttributes(array $schema, array $attributes, string $theme): array
    {
        $id = $schema['modal_id'] ?? null;
        if (!$id || ($schema['disabled'] ?? false)) { return $attributes; }
        if (($schema['form_method'] ?? '') === 'post' || in_array($schema['type'] ?? '', ['submit', 'reset'], true) || !empty($schema['lightbox'])) {
            throw new \InvalidArgumentException('Modal triggers cannot submit, reset or open a lightbox.');
        }
        $attributes['aria-haspopup'] = 'dialog';
        $attributes['aria-controls'] = $id;
        if ($theme === 'bootstrap') {
            $attributes['data-bs-toggle'] = 'modal';
            $attributes['data-bs-target'] = '#'.$id;
        } else {
            $attributes['data-wi-modal-target'] = '#'.$id;
        }
        return $attributes;
    }
}
