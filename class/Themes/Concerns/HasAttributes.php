<?php

namespace Wonder\Themes\Concerns;

trait HasAttributes
{
    use EscapesHtml;

    protected function renderAttributes(?array $attributes): string
    {
        $html = [];

        if ($attributes != null) {
            foreach ($attributes ?? [] as $key => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $html[] = $key;
                    }
                } elseif ($key === 'style' && is_array($value)) {
                    $style = $this->renderStyleDeclarations($value);
                    if ($style !== '') {
                        $html[] = $key . '="' . $this->escape($style) . '"';
                    }
                } elseif (is_array($value)) {
                    $attr = $key . '="';
                    foreach ($value as $k => $c) {
                        $attr .= $this->escape((string) $c) . ' ';
                    }
                    $attr .= '"';
                    $html[] = $attr;
                } else {
                    $html[] = $key . '="' . $this->escape((string) $value) . '"';
                }
            }
        }

        return implode(' ', $html);
    }

    private function normalizeStyleDeclarations(mixed $style): array
    {
        if (is_array($style)) {
            $normalized = [];

            foreach ($style as $property => $value) {
                if (!is_string($property) || trim($property) === '' || !is_scalar($value)) {
                    continue;
                }

                $normalized[strtolower(trim($property))] = trim((string) $value);
            }

            return $normalized;
        }

        if (!is_string($style) || trim($style) === '') {
            return [];
        }

        $normalized = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($property === '' || $value === '') {
                continue;
            }

            $normalized[$property] = $value;
        }

        return $normalized;
    }

    private function renderStyleDeclarations(mixed $style): string
    {
        $declarations = [];

        foreach ($this->normalizeStyleDeclarations($style) as $property => $value) {
            $declarations[] = $property . ': ' . $value . ';';
        }

        return implode(' ', $declarations);
    }
}
