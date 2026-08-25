<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsCheckGroupElement;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasInputType;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;
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
    use HasInputType;

    protected string $helper = 'checkTree';

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
            ->inputType($inputType)
            ->value($this->groupValue($inputType));
    }
}
