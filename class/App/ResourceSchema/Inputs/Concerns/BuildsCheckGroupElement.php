<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Wonder\Elements\Form\Components\CheckGroup;

/**
 * Costruzione del `CheckGroup` condivisa da {@see \Wonder\App\ResourceSchema\Inputs\InputRadio}
 * e dal {@see \Wonder\App\ResourceSchema\Inputs\InputCheckbox} con opzioni:
 * stesso Element, cambia solo il tipo di controllo reso.
 */
trait BuildsCheckGroupElement
{
    use NormalizesOptions;

    protected function checkGroupElement(string $inputType): CheckGroup
    {
        return (new CheckGroup($this->name))
            ->options($this->normalizedOptions())
            ->searchBar((bool) ($this->schema['search_bar'] ?? false))
            ->inputType($inputType)
            ->value($this->groupValue($inputType));
    }

    /**
     * Il value di un gruppo di checkbox può arrivare serializzato in JSON
     * (è la forma in cui `formToArray()` lo persiste): qui viene riportato ad
     * array. I radio, che portano sempre un valore singolo, restano intatti.
     *
     * Nota: oggi la decodifica è inerte, perché `Input::hydrate()` riscrive il
     * value con quello grezzo subito dopo. Il comportamento è quello che aveva
     * il vecchio dispatcher e va mantenuto finché non si decide come
     * cambiarlo: rimuoverlo qui nasconderebbe l'intenzione originale.
     */
    protected function groupValue(string $inputType): mixed
    {
        $value = $this->schema['value'] ?? null;

        if ($inputType !== 'checkbox' || !is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
