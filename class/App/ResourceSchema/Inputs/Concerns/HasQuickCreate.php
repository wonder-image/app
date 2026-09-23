<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Closure;

/**
 * Aggiunge a un input FK una "creazione rapida": un "+" apre un modal per
 * creare una riga della risorsa collegata e la aggiunge come opzione.
 * Vedi docs/app/concetti/form/quick-create.md.
 */
trait HasQuickCreate
{
    /**
     * @param string       $resourceClass FQCN della Resource collegata (deve esporre lo store API).
     * @param array|null   $fields        chiavi del sottoinsieme da mostrare; `null` = i campi obbligatori del target.
     * @param Closure|null $layout        layout custom del modal (ritorna un Form/Container/Card composto con
     *                                    `Target::getInput(...)`); `null` = i campi in un Container.
     * @param string|null  $label         campo che fa da etichetta dell'opzione; `null` = ripiego (name/title/primo campo).
     * @param string|null  $button        testo del bottone e titolo del modal; `null` = «Aggiungi <label() della risorsa>».
     */
    public function quickCreate(
        string $resourceClass,
        ?array $fields = null,
        ?Closure $layout = null,
        ?string $label = null,
        ?string $button = null
    ): static {
        return $this->context('quick_create', [
            'resource' => $resourceClass,
            'slug'     => $resourceClass::slug(),
            'fields'   => $fields === null ? null : array_values($fields),
            'layout'   => $layout,
            'label'    => $label,
            'button'   => $button,
        ]);
    }
}
