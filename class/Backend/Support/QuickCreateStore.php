<?php

namespace Wonder\Backend\Support;

/**
 * Una Resource che crea da sé la riga della creazione rapida.
 *
 * Di base il proxy chiama lo store API del target come `@system`. Chi
 * implementa questa interfaccia viene invece chiamato nel processo della
 * richiesta, con l'utente della sessione: serve a chi deve cercare un doppione
 * prima di creare, o legare la riga a chi la crea (es. il commerciale).
 * Permessi, CSRF e campi obbligatori li controlla comunque il proxy.
 */
interface QuickCreateStore
{
    /**
     * @param array<string,mixed> $values i campi del modal, senza quelli di controllo
     * @param string              $labelField il campo da usare come etichetta
     * @return array{id?:int,label?:string,item?:array<string,mixed>,message?:string,error?:string}
     *         `id` > 0 se è andata (anche una riga già esistente, con `message`),
     *         altrimenti `error`.
     */
    public static function quickCreateStore(array $values, string $labelField): array;
}
