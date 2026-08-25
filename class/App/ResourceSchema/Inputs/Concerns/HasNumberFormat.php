<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Configurazione del formatting numerico — mirror del DSL di
 * `Wonder\Elements\Form\Components\InputNumber` (da cui `InputPrice` e
 * `InputPercentige` ereditano gli stessi setters).
 *
 * I valori finiscono in `context['number']`; al render il
 * `FormFieldElementFactory::numberElement()` costruisce l'Element corretto e
 * ri-applica ognuno chiamando il metodo omonimo sull'Element, così
 * l'attributo `wi-number-*` emesso resta quello canonico della lib senza
 * duplicarne i nomi qui. Sono opt-in: senza chiamate, number/price/percentige
 * rendono esattamente come un Element appena costruito.
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
}
