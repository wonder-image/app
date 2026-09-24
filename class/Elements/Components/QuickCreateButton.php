<?php

namespace Wonder\Elements\Components;

use Closure;
use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/**
 * La creazione rapida staccata da un campo: un bottone che apre lo stesso
 * modal del "+" di `quickCreate(...)`, con gli stessi permessi e lo stesso
 * evento `wi:quick-create:created`.
 *
 * Serve dove la riga nuova non diventa un'opzione di un campo ma qualcosa
 * che la pagina mette in scena da sé: l'evento parte con `input: null` e la
 * riga salvata in `item`, e la raccoglie uno script della pagina.
 *
 * ```php
 * QuickCreateButton::make(AttributeResource::class)
 *     ->text('Nuova caratteristica')
 *     ->fields(['name', 'type', 'unit'])
 *     ->label('name')
 * ```
 *
 * Chi non può creare la risorsa non vede niente. Vedi
 * docs/app/concetti/form/quick-create.md.
 */
class QuickCreateButton extends Component
{
    use CanSpanColumn, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];

    /** @param string $resourceClass FQCN della Resource da creare (deve esporre lo store API). */
    public function __construct(string $resourceClass)
    {
        $this->schema('resource', $resourceClass)
            ->schema('slug', (string) $resourceClass::slug());
    }

    public static function make(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    /** Testo del bottone e titolo del modal; senza, «Aggiungi <label() della risorsa>». */
    public function text(string $text): self
    {
        return $this->schema('button', trim($text));
    }

    /** Chiavi dei campi del modal; senza (o `null`), i campi obbligatori della risorsa. */
    public function fields(?array $fields): self
    {
        return $this->schema('fields', $fields === null ? null : array_values($fields));
    }

    /**
     * Layout custom del corpo del modal: la closure ritorna un
     * Form/Container/Card composto con `Target::getInput(...)`.
     */
    public function layout(?Closure $layout): self
    {
        return $this->schema('layout', $layout);
    }

    /** Il campo che fa da etichetta della riga nuova (`detail.label`); senza, name/title/primo campo. */
    public function label(?string $field): self
    {
        $field = $field === null ? '' : trim($field);

        return $this->schema('label', $field === '' ? null : $field);
    }

    /** Dimensione del bottone, come `Button::size()`: '', 'sm' o 'lg'. */
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

    /**
     * La config nella forma di `quickCreate(...)`: la leggono `QuickCreatePanel`
     * e `QuickCreateModal`, gli stessi del "+" di un campo.
     *
     * @return array{resource:string,slug:string,fields:?array,layout:?Closure,label:?string,button:?string}
     */
    public function quickCreateConfig(): array
    {
        return [
            'resource' => (string) ($this->schema['resource'] ?? ''),
            'slug' => (string) ($this->schema['slug'] ?? ''),
            'fields' => $this->schema['fields'] ?? null,
            'layout' => $this->schema['layout'] ?? null,
            'label' => $this->schema['label'] ?? null,
            'button' => $this->schema['button'] ?? null,
        ];
    }
}
