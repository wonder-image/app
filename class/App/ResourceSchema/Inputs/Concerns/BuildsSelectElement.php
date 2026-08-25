<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Wonder\Elements\Form\Components\Select;

/**
 * Costruzione del `Select` condivisa dai tipi che rendono una tendina:
 * {@see \Wonder\App\ResourceSchema\Inputs\InputSelect} e la sua variante
 * ricercabile, ma anche i tre tipi a lista precompilata
 * (`InputCountry`, `InputStates`, `InputPhonePrefix`) che non fanno parte di
 * quella gerarchia ma renderizzano lo stesso controllo.
 */
trait BuildsSelectElement
{
    use NormalizesOptions;

    /**
     * @param array<array-key, mixed>|null $options Opzioni esplicite; `null` usa quelle del campo.
     */
    protected function selectElement(?array $options = null): Select
    {
        return (new Select($this->name))->options(
            $options === null ? $this->normalizedOptions() : $this->normalizeOptions($options)
        );
    }

    /**
     * Variante ricercabile lato client (`data-wi-select-search`), che con
     * `multiple` attivo abilita anche la selezione multipla.
     *
     * @param array<array-key, mixed>|null $options
     */
    protected function searchableSelectElement(?array $options = null): Select
    {
        $select = $this->selectElement($options);

        $select->attr('data-wi-select-search', 'true');

        if ((bool) ($this->schema['multiple'] ?? false)) {
            $select->attr('data-wi-select-search-multiple', 'true');
            $select->attr('multiple', true);
        }

        return $select;
    }
}
