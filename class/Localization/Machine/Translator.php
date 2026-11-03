<?php

namespace Wonder\Localization\Machine;

/**
 * Motore di traduzione automatica. Riceve frammenti HTML (il testo semplice
 * arriva già escapato) e deve lasciare intatto il contenuto degli elementi
 * con `translate="no"`. Restituisce le traduzioni nello stesso ordine.
 */
interface Translator
{
    public function key(): string;

    /**
     * @param list<string> $html
     * @return list<string>
     */
    public function translate(array $html, string $source, string $target): array;
}
