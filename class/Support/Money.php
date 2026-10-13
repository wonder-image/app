<?php

namespace Wonder\Support;

use NumberFormatter;
use Wonder\Localization\LanguageContext;

/**
 * Un importo come lo scrive la lingua del sito: «1.234,50 €» in italiano,
 * «€1,234.50» in inglese.
 *
 * La lingua è quella di `LanguageContext` (la lingua corrente, o quella
 * predefinita del sito). Con l'estensione `intl` decide `NumberFormatter`;
 * senza, l'inglese mette la valuta davanti e il punto per i decimali, tutte
 * le altre lingue scrivono all'italiana. Gli spazi speciali di `intl` (non
 * divisibili) diventano spazi normali, come il resto della pagina.
 */
final class Money
{
    public static function format(float $amount, ?string $lang = null, string $currency = 'EUR'): string
    {
        $lang = strtolower(trim((string) ($lang ?? LanguageContext::getLang()))) ?: 'it';

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($lang, NumberFormatter::CURRENCY);
            $text = $formatter->formatCurrency($amount, $currency);

            if (is_string($text) && $text !== '') {
                return str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);
            }
        }

        return self::fallback($amount, $lang, $currency);
    }

    /** Il formato senza `intl`: l'inglese all'inglese, il resto all'italiana. */
    public static function fallback(float $amount, string $lang, string $currency = 'EUR'): string
    {
        $symbol = $currency === 'EUR' ? '€' : $currency;
        $sign = $amount < 0 ? '-' : '';
        $value = abs($amount);

        if (str_starts_with($lang, 'en')) {
            return $sign.$symbol.number_format($value, 2, '.', ',');
        }

        return $sign.number_format($value, 2, ',', '.').' '.$symbol;
    }
}
