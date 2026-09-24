<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Wonder\Elements\Form\Components\InputNumber as NumberElement;

/**
 * Configurazione del formatting numerico — mirror del DSL di
 * `Wonder\Elements\Form\Components\InputNumber` (da cui `InputPrice` e
 * `InputPercentige` ereditano gli stessi setters).
 *
 * I valori finiscono in `context['number']` e {@see applyNumberConfig()} li
 * ri-applica all'Element chiamando i metodi omonimi, così l'attributo
 * `wi-number-*` emesso resta quello canonico della lib senza duplicarne i
 * nomi qui. Senza chiamate, number/price/percentige rendono esattamente come
 * un Element appena costruito, che porta già il formato italiano: virgola
 * decimale e niente migliaia per numero e percentuale, «1.299,90 €» per il
 * prezzo. Una config esplicita vince sempre, anche quando è vuota:
 * `groupSeparator('')` toglie il punto delle migliaia al prezzo e
 * `symbol('')` gli toglie il «€». Un `decimalSeparator('')` invece è
 * ignorato, perché un numero senza separatore decimale non si scrive.
 *
 * `decimal()` limita le cifre decimali mostrate (attributo lib), mentre
 * `decimals()` passa il valore allo schema dell'Element: nomi vicini ma
 * concetti distinti, mantenuti entrambi per fedeltà all'API dell'Element.
 */
trait HasNumberFormat
{
    use WritesNumberConfig;

    public function decimal(int $decimal): static
    {
        return $this->numberConfig('decimal', max(0, $decimal));
    }

    public function decimalSeparator(string $separator): static
    {
        return $this->numberConfig('decimal_separator', $separator);
    }

    public function groupSeparator(string $separator): static
    {
        return $this->numberConfig('group_separator', $separator);
    }

    public function symbol(string $symbol): static
    {
        return $this->numberConfig('symbol', $symbol);
    }

    public function symbolPlacement(string $placement): static
    {
        return $this->numberSymbolPlacement($placement);
    }

    /**
     * Numero intero: nessuna cifra decimale mostrata. Equivale a `decimal(0)`.
     */
    public function integer(): static
    {
        return $this->decimal(0);
    }

    /**
     * Testo in coda al numero, per l'unità di misura: `suffix(' kg')` mostra
     * «2,50 kg». Equivale a `symbol($text)->symbolPlacement('s')`.
     *
     * Occupa lo stesso posto del simbolo di valuta e lo sostituisce: su un
     * prezzo toglierebbe il «€», quindi non va usato sui prezzi.
     */
    public function suffix(string $text): static
    {
        return $this->symbol($text)->symbolPlacement('s');
    }

    public function decimals(int $decimals): static
    {
        return $this->numberConfig('decimals', max(0, $decimals));
    }

    /**
     * Copia la config raccolta in `context['number']` sull'Element numerico
     * appena costruito (`InputNumber`, `InputPrice`, `InputPercentige`).
     */
    protected function applyNumberConfig(NumberElement $element): NumberElement
    {
        $number = (array) ($this->schema['context']['number'] ?? []);

        if (isset($number['decimal']) && is_numeric($number['decimal'])) {
            $element->decimal((int) $number['decimal']);
        }

        if (isset($number['decimal_separator']) && is_string($number['decimal_separator']) && $number['decimal_separator'] !== '') {
            $element->decimalSeparator($number['decimal_separator']);
        }

        // Qui la stringa vuota è una scelta ("nessun separatore", "nessun
        // simbolo") e deve sovrascrivere il default dell'Element: la lib
        // legge '' dal dataset e lo applica.
        if (isset($number['group_separator']) && is_string($number['group_separator'])) {
            $element->groupSeparator($number['group_separator']);
        }

        if (isset($number['symbol']) && is_string($number['symbol'])) {
            $element->symbol($number['symbol']);
        }

        if (isset($number['symbol_placement']) && in_array($number['symbol_placement'], ['p', 's'], true)) {
            $element->symbolPlacement($number['symbol_placement']);
        }

        if (isset($number['decimals']) && is_numeric($number['decimals'])) {
            $element->decimals((int) $number['decimals']);
        }

        return $element;
    }
}
