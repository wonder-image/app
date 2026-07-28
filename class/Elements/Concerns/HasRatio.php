<?php

namespace Wonder\Elements\Concerns;

use InvalidArgumentException;

/**
 * Rapporto d'aspetto (format) per un box contenitore.
 *
 * Emette la proprietà CSS nativa `aspect-ratio` più un contesto di
 * posizionamento (`position: relative`), quindi funziona in qualunque tema
 * (Bootstrap, Wonder) senza dipendere dalle classi specifiche `.ratio-*` o
 * `f-*`, e copre qualsiasi rapporto: `16:9`, `9:16`, `3:2`, `2:3`, …
 *
 * I separatori accettati sono `:`, `x`, `/` e `-` (case-insensitive):
 * `->ratio('16:9')`, `->format('9x16')`, `->ratio('3/2')` sono equivalenti.
 */
trait HasRatio
{
    use ParsesRatio;

    public function ratio(string $ratio): static
    {
        [$width, $height] = self::parseRatio($ratio)
            ?? throw new InvalidArgumentException("Rapporto d'aspetto non valido: {$ratio}");

        // `no-grid`: un box a rapporto fisso non è anche una griglia; evita che
        // il renderer del Container aggiunga row/gutter attorno al contenuto.
        return $this
            ->style('aspect-ratio', $width . ' / ' . $height)
            ->style('position', 'relative')
            ->schema('no-grid', true);
    }

    /** Alias di {@see ratio()} — "format" nel vocabolario media della lib. */
    public function format(string $ratio): static
    {
        return $this->ratio($ratio);
    }
}
