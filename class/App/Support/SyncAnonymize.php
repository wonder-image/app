<?php

namespace Wonder\App\Support;

/**
 * Regola di anonimizzazione di una colonna di una tabella `content()` copiata
 * in staging. Qui c'è solo il vocabolario: chi sostituisce davvero i valori
 * durante la copia è il comando di sincronizzazione (PR successive).
 */
enum SyncAnonymize: string
{
    case Name = 'name';
    case Surname = 'surname';
    case Email = 'email';
    case Phone = 'phone';
    case Username = 'username';
    case Address = 'address';
    case Text = 'text';
    /** Svuota il valore (password, token, immagini personali). */
    case Clear = 'clear';
}
