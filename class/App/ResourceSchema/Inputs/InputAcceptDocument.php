<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputAcceptDocument as AcceptDocumentElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Checkbox di accettazione di un documento legale (privacy, terms, ...).
 *
 * Il `name` del campo deve coincidere con `accept_<type>`: `user()`
 * (app/function/user/user.php) e `ConsentService` cercano proprio quel
 * prefisso nel POST per registrare il consenso in `consent_events` /
 * `user_consent_state`.
 *
 * I dati del documento (id, label HTML, ...) vengono risolti da questa classe
 * al render per la lingua corrente, leggendo `context['document_type']`. Se il
 * documento non esiste il campo rende stringa vuota invece di sollevare.
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

    /**
     * Se il documento non esiste per la lingua corrente il campo rende
     * stringa vuota invece di sollevare: un form senza quel documento resta
     * comunque utilizzabile.
     */
    public function render(?string $theme = null): string
    {
        return $this->legalDocument() === null ? '' : parent::render($theme);
    }

    protected function element(): ?ElementField
    {
        $document = $this->legalDocument();

        if ($document === null) {
            return null;
        }

        $element = (new AcceptDocumentElement($this->name))
            ->documentType((string) ($this->schema['context']['document_type'] ?? ''))
            ->documentId((int) ($document->id ?? 0))
            ->documentLabel((string) ($document->renderLabel ?? ''));

        return $this->isAccepted() ? $element->checked() : $element;
    }

    /** Il consenso già dato arriva come booleano, o come array di booleani. */
    private function isAccepted(): bool
    {
        $value = $this->schema['value'] ?? null;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (filter_var($item, FILTER_VALIDATE_BOOLEAN)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Ultima versione attiva del documento dichiarato in
     * `context['document_type']`, nella lingua corrente.
     */
    private function legalDocument(): ?object
    {
        $type = (string) ($this->schema['context']['document_type'] ?? '');

        if ($type === '') {
            return null;
        }

        if (
            class_exists(\Wonder\Consent\LegalDocumentTypeContext::class)
            && !\Wonder\Consent\LegalDocumentTypeContext::hasType($type)
            && function_exists('__log')
        ) {
            __log(new \Exception('Non esiste il documento '.$type), 'wonder-renderer', 'verify_document_type');
        }

        if (!function_exists('sqlSelect') || !function_exists('infoLegalDocument') || !function_exists('__l')) {
            return null;
        }

        $SQL = sqlSelect(
            'legal_documents',
            [
                'doc_type' => $type,
                'language_code' => __l(),
                'active' => 'true',
            ],
            1,
            'published_at DESC, id',
            'DESC'
        );

        if (!$SQL->exists) {
            if (function_exists('__log')) {
                __log(new \Exception('Non esiste il documento '.$type.' nella lingua '.__l()), 'wonder-renderer', 'verify_document_type_lang');
            }

            return null;
        }

        $document = infoLegalDocument($SQL->id);

        return is_object($document) ? $document : null;
    }
}
