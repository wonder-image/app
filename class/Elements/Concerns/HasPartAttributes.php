<?php

namespace Wonder\Elements\Concerns;

/**
 * Classi e attributi delle parti interne di un Element (il corpo della
 * Modal, il menu del Dropdown, ...).
 *
 * L'Element espone un metodo pubblico per ogni parte che possiede davvero
 * (`bodyClass()`, `menuClass()`, ...), che delega qui in una riga: una parte
 * inesistente resta un errore a tempo di scrittura. Le classi si aggiungono a
 * quelle del tema; il renderer le scrive con
 * `Themes\Concerns\RendersPartAttributes::partAttributes()`.
 */
trait HasPartAttributes
{
    protected function setPartClass(string $part, string $class): static
    {
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
            if ($token !== '') {
                $this->schema['parts'][$part]['class'][] = $token;
            }
        }

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    protected function setPartAttributes(string $part, array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            if (trim($key) === 'class') {
                $this->setPartClass($part, is_array($value) ? implode(' ', $value) : (string) $value);
                continue;
            }

            $this->schema['parts'][$part]['attributes'][trim($key)] = $value;
        }

        return $this;
    }

    /** @return array{class: string[], attributes: array<string, mixed>} */
    protected function partSchema(string $part): array
    {
        $schema = $this->schema['parts'][$part] ?? [];

        return [
            'class' => array_values((array) ($schema['class'] ?? [])),
            'attributes' => (array) ($schema['attributes'] ?? []),
        ];
    }
}
