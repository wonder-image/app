<?php

namespace Wonder\App\Support;

/**
 * A quale delle tre classi appartiene una tabella nella sincronizzazione
 * produzione → staging.
 *
 * - `Code`: non si copia; arriva dal repo col deploy e dopo la copia dei
 *   contenuti si riapplica (per esempio le tabelle `css_*`).
 * - `Content`: si modifica solo in produzione; in staging si copia da lì e le
 *   modifiche fatte in staging si perdono alla copia successiva.
 * - `Environment`: non si copia e non si tocca mai: utenti di backend,
 *   sessioni, chiavi, log, consensi, code di lavoro, impostazioni dell'ambiente.
 *
 * Non c'entra con {@see SyncSchema::singleton()} / {@see SyncSchema::multiRow()}:
 * quelle dicono se una tabella finisce in `shared/sync-data.json`, questa dice
 * che cosa fa la tabella fra gli ambienti.
 */
enum SyncClass: string
{
    case Code = 'code';
    case Content = 'content';
    case Environment = 'environment';
}
