<?php

namespace Wonder\Elements\Concerns;

trait ParsesRatio
{
    /**
     * Interpreta un rapporto testuale in una coppia [larghezza, altezza].
     *
     * @return array{0:int,1:int}|null coppia positiva, o null se non valido
     */
    protected static function parseRatio(string $ratio): ?array
    {
        if (preg_match('/^\s*(\d{1,4})\s*[:x\/-]\s*(\d{1,4})\s*$/i', $ratio, $matches) !== 1) {
            return null;
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];

        return ($width > 0 && $height > 0) ? [$width, $height] : null;
    }
}
