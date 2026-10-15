<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasQuickCreate;
use Wonder\Elements\Form\Components\SearchRemote;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Base delle ricerche remote: un input che popola la propria dropdown via
 * AJAX dall'endpoint dato.
 *
 * L'URL vive in `schema['url']` (non in `context`, dove lo tiene invece
 * {@see InputDynamicCheck}); il tipo di ricerca lo decide la sottoclasse
 * tramite l'helper — {@see InputSearchText} o {@see InputSearchRadio}.
 */
abstract class InputSearchRemote extends Input
{
    use HasQuickCreate;

    public function url(string $url): static
    {
        $this->schema['url'] = trim($url);

        return $this;
    }

    /** Il testo della voce già scelta: il value è il suo id. */
    public function valueLabel(string $label): static
    {
        $this->schema['value_label'] = $label;

        return $this;
    }

    /** La frase sotto la casella quando la ricerca non trova nulla. */
    public function emptyText(string $text): static
    {
        $this->schema['empty_text'] = $text;

        return $this;
    }

    /** `text` o `radio`: lo decide la sottoclasse. */
    abstract protected function searchType(): string;

    protected function element(): ElementField
    {
        $element = (new SearchRemote($this->name))
            ->url((string) ($this->schema['url'] ?? ''))
            ->searchType($this->searchType());

        if (isset($this->schema['value_label'])) {
            $element->valueLabel((string) $this->schema['value_label']);
        }

        if (isset($this->schema['empty_text'])) {
            $element->emptyText((string) $this->schema['empty_text']);
        }

        return $element;
    }
}
