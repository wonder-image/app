<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsCheckGroupElement;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasInputType;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasQuickCreate;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;
use Wonder\App\ResourceSchema\Inputs\Concerns\ListsResource;
use Wonder\Elements\Form\Components\CheckTree;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Lista ad albero (jsTree) di checkbox o radio.
 *
 * Le opzioni ammettono la forma estesa con figli:
 * `['id' => ['name' => 'Label', 'child' => [...]]]`.
 */
class InputCheckTree extends Input
{
    use BuildsCheckGroupElement;
    use HasOptions;
    use HasSearchBar;
    use ListsResource;
    use HasQuickCreate;
    use HasInputType;

    protected string $helper = 'checkTree';

    /**
     * La voce principale fra quelle spuntate, segnata con una stella.
     *
     * `$field` è il nome di un altro campo del form (di solito nascosto) in
     * cui finisce l'id della voce con la stella. La lib lo tiene aggiornato:
     * la prima spunta prende la stella, un clic sull'icona di un'altra voce
     * spuntata la sposta, e togliere la spunta alla principale la passa alla
     * prima rimasta. Solo per gli alberi a checkbox.
     */
    public function primaryField(string $field): static
    {
        $this->schema['primary_field'] = $field;

        return $this;
    }

    /**
     * Come per i gruppi di checkbox, un value serializzato in JSON viene
     * riportato ad array (vedi la nota in {@see Concerns\BuildsCheckGroupElement}
     * sulla riscrittura successiva del value).
     */
    protected function element(): ElementField
    {
        $inputType = (string) ($this->schema['context']['input_type'] ?? 'checkbox');

        return (new CheckTree($this->name))
            ->options($this->normalizedOptions())
            ->searchBar((bool) ($this->schema['search_bar'] ?? false))
            ->listsResource((string) ($this->schema['lists_resource'] ?? ''))
            ->primaryField((string) ($this->schema['primary_field'] ?? ''))
            ->inputType($inputType)
            ->value($this->groupValue($inputType));
    }
}
