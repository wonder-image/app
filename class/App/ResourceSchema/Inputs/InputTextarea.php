<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasVersion;
use Wonder\Elements\Form\Components\Textarea;
use Wonder\Elements\Form\Components\TextareaEditor;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Area di testo multilinea.
 *
 * Senza `version()` rende una `Textarea` semplice; con una version costruisce
 * e configura il `TextareaEditor` rich-text col preset omonimo
 * (es. `'plus'`, `'blog'`, `'old'`).
 */
class InputTextarea extends Input
{
    use HasVersion;

    protected string $helper = 'textarea';

    /**
     * Senza `version()` una `Textarea` semplice; con una version l'editor
     * rich-text, agganciato alla cartella upload del contenuto corrente
     * (`$GLOBALS['NAME']->folder`) per il file manager interno.
     */
    protected function element(): ElementField
    {
        $version = $this->schema['version'] ?? null;

        if (!is_string($version) || trim($version) === '') {
            return new Textarea($this->name);
        }

        return (new TextareaEditor($this->name))
            ->version(trim($version))
            ->folder($this->legacyFolder());
    }

    private function legacyFolder(): ?string
    {
        $name = $GLOBALS['NAME'] ?? null;

        if (!is_object($name)) {
            return null;
        }

        $folder = trim((string) ($name->folder ?? ''));

        return $folder !== '' ? $folder : null;
    }
}
