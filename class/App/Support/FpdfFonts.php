<?php

namespace Wonder\App\Support;

/**
 * Accessor della tabella font FPDF del framework.
 *
 * `$FONT_FPDF` è definita in `app/config/array/fpdf.php` e caricata nei globals
 * dal bootstrap del sito (config.php → array.php → fpdf.php). Gli schema delle
 * Resource, però, vengono letti anche in pre-routing, prima che i globals siano
 * popolati: in quel caso questo accessor carica il file di configurazione
 * on-demand, così moduli e siti ottengono sempre l'elenco completo dei font
 * senza doverlo duplicare.
 */
final class FpdfFonts
{
    /** @var array<string, string>|null cache per-request */
    private static ?array $cache = null;

    /**
     * Mappa font FPDF: chiave = nome file font, valore = etichetta leggibile.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $global = $GLOBALS['FONT_FPDF'] ?? null;

        if (is_array($global) && $global !== []) {
            return self::$cache = self::normalize($global);
        }

        $file = self::configFile();

        if ($file !== '' && is_file($file)) {
            $FONT_FPDF = [];
            require $file; // definisce $FONT_FPDF in questo scope

            if (is_array($FONT_FPDF) && $FONT_FPDF !== []) {
                return self::$cache = self::normalize($FONT_FPDF);
            }
        }

        // Fallback ai soli font core FPDF, sempre disponibili.
        return self::$cache = [
            'helvetica' => 'Arial',
            'times'     => 'Times',
            'courier'   => 'Courier',
        ];
    }

    /** Percorso del file di configurazione font del framework. */
    private static function configFile(): string
    {
        // class/App/Support/FpdfFonts.php → radice del pacchetto framework.
        return dirname(__DIR__, 3).'/app/config/array/fpdf.php';
    }

    /**
     * @param array<int|string, mixed> $fonts
     * @return array<string, string>
     */
    private static function normalize(array $fonts): array
    {
        $normalized = [];

        foreach ($fonts as $key => $label) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $normalized[$key] = (string) $label;
        }

        return $normalized;
    }
}
