<?php

namespace Wonder\Auth\Frontend;

/** Aggancio di un modulo al pannello account del core: route, menu, righe, campi e validazione dei dati personali. */
interface AccountExtension
{
    /** Registra le route del modulo, con `AccountRoutes::group()` per quelle private. */
    public function routes(): void;

    /** @param array<string, array> $items voci del menu per chiave; @return array<string, array> */
    public function navigation(array $items, object $user): array;

    public function overviewRows(array $rows, object $user): array;

    public function personalRows(array $rows, object $user): array;

    public function personalFields(array $fields, object $user): array;

    /** @return list<string> messaggi già tradotti, vuoto se i dati vanno bene */
    public function validatePersonal(array $input, object $user): array;

    /** Valori da scrivere sull'utente, oltre a quelli del core. */
    public function personalUserValues(array $input, object $user): array;

    public function afterPersonalSaved(array $input, object $user): void;

    /** Markup da aggiungere nell'head delle pagine del pannello. */
    public function head(): string;
}
