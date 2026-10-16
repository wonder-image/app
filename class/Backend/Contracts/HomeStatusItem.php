<?php

namespace Wonder\Backend\Contracts;

use Wonder\Elements\Components\StatusCard;

/**
 * Voce della fascia di stato in cima alla home del backend. Il core registra
 * `scheduler` ed `errors`; moduli e sito le sostituiscono o le tolgono per
 * chiave (`backend.home_status`, `HomeStatus::set()`).
 */
interface HomeStatusItem
{
    /**
     * Card della voce, o null per nasconderla. Controlla i permessi prima di
     * leggere i dati, di norma con `Resource::allows()` della pagina a cui
     * porta il link.
     *
     * @param list<string> $authorities autorità dell'utente
     */
    public function status(array $authorities, string $backendUrl): ?StatusCard;

    /** Ordine crescente. */
    public function order(): int;
}
