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
 * nomi qui. Sono opt-in: senza chiamate, number/price/percentige rendono
 * esattamente come un Element appena costruito.
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

        if (isset($number['group_separator']) && is_string($number['group_separator']) && $number['group_separator'] !== '') {
            $element->groupSeparator($number['group_separator']);
        }

        if (isset($number['symbol']) && is_string($number['symbol']) && $number['symbol'] !== '') {
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
