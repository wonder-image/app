<?php

namespace Wonder\Elements\Form\Components;

/**
 * Checkbox di accettazione di un documento legale (privacy, terms, …).
 * I dati del documento (id, label HTML, version, ecc.) vengono
 * forniti dall'esterno via setters: l'Element non fa I/O, l'I/O resta
 * nel chiamante (`inputAcceptDocument()` in `frontend/input.php`)
 * che ha accesso alle utility legacy `sqlSelect`/`infoLegalDocument`.
 */
class InputAcceptDocument extends Checkbox
{
    /**
     * Tipo del documento (es. "privacy", "terms"). Usato come prefisso
     * del nome del campo nascosto `<type>_id` che trasporta l'id del
     * documento accettato.
     */
    public function documentType(string $type): self
    {
        return $this->schema('document_type', trim($type));
    }

    /**
     * Id univoco del documento attivo a database. Renderizzato come
     * `<input type="hidden" name="<documentType>_id" value="…">`.
     */
    public function documentId(int $id): self
    {
        return $this->schema('document_id', $id);
    }

    /**
     * Label del documento (può contenere HTML come `<a>` verso la
     * versione pubblica). Sostituisce la label normale del campo.
     */
    public function documentLabel(string $html): self
    {
        return $this->schema('document_label', $html);
    }
}
