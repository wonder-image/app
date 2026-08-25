<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Checkbox di accettazione di un documento legale (privacy, terms, ...).
 *
 * Il `name` del campo deve coincidere con `accept_<type>`: `user()`
 * (app/function/user/user.php) e `ConsentService` cercano proprio quel
 * prefisso nel POST per registrare il consenso in `consent_events` /
 * `user_consent_state`.
 *
 * I dati del documento (id, label HTML, ...) vengono risolti al render per la
 * lingua corrente in `FormFieldElementFactory::resolveLegalDocument()`,
 * leggendo `context['document_type']`. Se il documento non esiste il campo
 * rende stringa vuota invece di sollevare.
 */
class InputAcceptDocument extends Input
{
    protected string $helper = 'inputAcceptDocument';

    /**
     * Tipo di documento (es. `privacy_policy`). Normalizzato a lowercase con
     * i soli caratteri `[a-z0-9_-]`.
     */
    public function documentType(string $type): static
    {
        $type = strtolower(trim($type));
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        return $this->context('document_type', $type);
    }
}
