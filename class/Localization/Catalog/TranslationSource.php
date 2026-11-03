<?php

namespace Wonder\Localization\Catalog;

/**
 * Una sorgente di testi traducibili per la pagina Traduzioni: i JSON del
 * sito, i documenti legali, i campi di un Model. Legge le righe per una
 * coppia di lingue e applica le traduzioni confermate dall'import.
 */
interface TranslationSource
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, Entry> indicizzate per id */
    public function entries(string $source, string $target): array;

    /**
     * Scrive le traduzioni scelte (`id => testo`). Non cancella mai: una
     * chiave assente resta com'è.
     *
     * @param array<string, string> $changes
     * @return array<string, string> errori per id
     */
    public function apply(string $source, string $target, array $changes): array;
}
