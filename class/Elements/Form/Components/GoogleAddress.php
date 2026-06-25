<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Campo "Google Places Address": un input testo che mostra l'address
 * formattato e 6 hidden field (country/province/city/cap/street/
 * number) compilati dal JS al place_changed dell'autocomplete.
 *
 * Esiste un wrapper procedurale separato lato backend (`googleAddress()`
 * in `backend/input.php`) che è molto più semplice — non rende gli
 * hidden, ma decora un `text()` con `data-wi-search-place=true` e
 * `data-wi-callback`. Qui parliamo della versione frontend full
 * dove il submit del form deve trasportare l'address breakdown.
 */
class GoogleAddress extends Field
{
    public string $type = 'text';

    /**
     * Limita la ricerca a determinati paesi/regioni (passato come
     * `data-wi-restriction` JSON). Default `[]` = nessuna restriction.
     */
    public function restriction(array $restriction): self
    {
        return $this->schema('restriction', $restriction);
    }

    /**
     * Prefisso opzionale per i nomi dei 6 hidden field. Quando `$name`
     * è "address" (default) il prefisso è vuoto e i campi sono
     * `country`, `province`, …; altrimenti diventano `<prefix>_country`,
     * `<prefix>_province`, … per supportare più indirizzi sullo
     * stesso form.
     */
    public function alias(string $alias): self
    {
        return $this->schema('alias', trim($alias));
    }

    /**
     * Address breakdown precompilato. Atteso array con chiavi
     * `country`, `province`, `city`, `cap`, `street`, `number`
     * (prefissate da `alias` se non default).
     */
    public function breakdown(array $values): self
    {
        return $this->schema('breakdown', $values);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
