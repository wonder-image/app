<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Aggiunge a un input FK una "creazione rapida": un "+" apre un modal per
 * creare una riga della risorsa collegata (sottoinsieme di campi) e la aggiunge
 * come opzione. Vedi docs/app/concetti/form/quick-create.md.
 */
trait HasQuickCreate
{
    /**
     * @param string      $resourceClass FQCN della Resource collegata (deve esporre lo store API).
     * @param array       $fields        chiavi del sottoinsieme da mostrare nel modal.
     * @param string|null $label         campo che fa da etichetta dell'opzione (default: label del target).
     */
    public function quickCreate(string $resourceClass, array $fields, ?string $label = null): static
    {
        return $this->context('quick_create', [
            'resource' => $resourceClass,
            'slug'     => $resourceClass::slug(),
            'fields'   => array_values($fields),
            'label'    => $label,
        ]);
    }
}
