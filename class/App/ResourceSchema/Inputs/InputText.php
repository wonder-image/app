<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputText as TextElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo testo semplice. Mirror DSL del legacy `FormField::key('x')->text()`,
 * ma usabile direttamente come prima classe (`InputText::key('x')`) senza
 * passare dalla facade `FormField`.
 *
 * Estende `Input` per ereditare label/attribute/required/value/render/etc. e
 * costruisce direttamente il proprio `Elements\Form\Components\InputText`.
 * È anche il tipo di un `FormField` senza type-helper.
 */
class InputText extends Input
{
    protected string $helper = 'text';

    /**
     * Numero massimo di caratteri, reso come attributo `maxlength` dai temi.
     * Uno zero o un negativo tolgono il limite.
     */
    public function maxLength(int $length): static
    {
        $this->schema['max_length'] = $length > 0 ? $length : null;

        return $this;
    }

    /**
     * La via di un indirizzo con l'autocompletamento di Google (`places.js`
     * della lib), per i campi che non passano da `AddressExtension`, come la
     * colonna di un repeater.
     *
     * $prefix è il prefisso dei campi fratelli (`''`, `shipping_`); in una
     * riga di repeater finisce con `[` (`addresses[`) e la lib legge la riga
     * dal nome della via. Con $names cerca anche le attività per nome; con
     * $nameKey (chiave senza prefisso) il nome dell'attività scelta va in quel
     * campo della stessa riga o gruppo, solo se è vuoto.
     */
    public function placeSearch(string $prefix = '', bool $names = false, ?string $nameKey = null): static
    {
        $attributes = 'data-wi-place="'.htmlspecialchars($prefix, ENT_QUOTES).'"';

        if ($names) {
            $attributes .= ' data-wi-place-names';
        }

        $nameKey = trim((string) $nameKey);

        if ($nameKey !== '') {
            $attributes .= ' data-wi-place-name="'.htmlspecialchars($nameKey, ENT_QUOTES).'"';
        }

        return $this->attribute($attributes);
    }

    protected function element(): ElementField
    {
        $element = new TextElement($this->name);

        $maxLength = $this->schema['max_length'] ?? null;

        if (is_int($maxLength) && $maxLength > 0) {
            $element->maxLength($maxLength);
        }

        return $element;
    }
}
