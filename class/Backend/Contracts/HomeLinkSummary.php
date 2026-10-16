<?php

namespace Wonder\Backend\Contracts;

/**
 * Dato sintetico di un link della home del backend: un badge accanto al
 * titolo (di norma un numero) e una riga secondaria sotto. Una Resource lo
 * collega alla propria voce con `NavigationSchema::homeSummary($class)`; la
 * classe viene istanziata solo dalla home, mai dalla sidebar.
 */
interface HomeLinkSummary
{
    /**
     * Badge e riga secondaria del link, entrambi facoltativi; null per non
     * mostrare nulla. Il link è già filtrato per autorità.
     *
     * @param list<string> $authorities autorità dell'utente
     * @return array{badge?: string, detail?: string}|null
     */
    public function summary(array $authorities): ?array;
}
