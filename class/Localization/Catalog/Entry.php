<?php

namespace Wonder\Localization\Catalog;

/**
 * Una riga del catalogo: id stabile, contesto per il traduttore, testo nella
 * lingua sorgente e, se c'è, nella lingua di destinazione. `group` lega le
 * righe di una lista che va scritta tutta insieme; `locked` spiega perché la
 * riga non si può più cambiare (per esempio un documento già accettato).
 */
final class Entry
{
    public function __construct(
        public readonly string $id,
        public readonly string $context,
        public readonly string $source,
        public readonly string $target = '',
        public readonly ?string $group = null,
        public readonly ?string $locked = null,
    ) {
    }
}
