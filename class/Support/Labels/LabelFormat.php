<?php

namespace Wonder\Support\Labels;

/**
 * Un formato di etichette, in millimetri: il foglio, la griglia e la misura
 * di una etichetta. Un rotolo termico è un foglio grande quanto l'etichetta,
 * una colonna e una riga.
 */
final class LabelFormat
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly float $pageWidth,
        public readonly float $pageHeight,
        public readonly int $columns,
        public readonly int $rows,
        public readonly float $labelWidth,
        public readonly float $labelHeight,
        public readonly float $marginTop = 0.0,
        public readonly float $marginLeft = 0.0,
        public readonly float $gapX = 0.0,
        public readonly float $gapY = 0.0,
    ) {
    }

    /** Un rotolo: una etichetta per pagina. */
    public static function roll(string $key, string $name, float $width, float $height): self
    {
        return new self($key, $name, $width, $height, 1, 1, $width, $height);
    }

    /** Un foglio A4 a griglia senza spazi fra le etichette, centrata. */
    public static function a4(string $key, string $name, int $columns, int $rows, float $width, float $height): self
    {
        return new self(
            $key,
            $name,
            210.0,
            297.0,
            $columns,
            $rows,
            $width,
            $height,
            max(0.0, (297.0 - $rows * $height) / 2),
            max(0.0, (210.0 - $columns * $width) / 2),
        );
    }

    /**
     * I formati standard: tre fogli A4 adesivi e due rotoli termici.
     *
     * @return array<string, self>
     */
    public static function standard(): array
    {
        $formats = [
            self::a4('a4-24', 'A4, 24 etichette (70 × 37 mm)', 3, 8, 70.0, 37.0),
            self::a4('a4-40', 'A4, 40 etichette (52,5 × 29,7 mm)', 4, 10, 52.5, 29.7),
            self::a4('a4-65', 'A4, 65 etichette (38,1 × 21,2 mm)', 5, 13, 38.1, 21.2),
            self::roll('roll-50x30', 'Rotolo termico 50 × 30 mm', 50.0, 30.0),
            self::roll('roll-40x25', 'Rotolo termico 40 × 25 mm', 40.0, 25.0),
        ];

        return array_combine(array_map(static fn (self $format): string => $format->key, $formats), $formats);
    }

    public function perPage(): int
    {
        return $this->columns * $this->rows;
    }

    public function isRoll(): bool
    {
        return $this->perPage() === 1;
    }
}
