<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Le voci di un gruppo (spunte o radio) come pillole in linea. Finisce in
 * `schema['pills']`, che `BuildsCheckGroupElement` passa all'Element.
 */
trait HasPills
{
    /**
     * Le voci come pillole in linea, senza il riquadro che scorre.
     *
     * Una colonna di spunte alta centoventi pixel dice «qui c'è un elenco
     * lungo»; cinque taglie non sono un elenco lungo, e incolonnarle in un
     * riquadro con la barra di scorrimento occupa dieci volte lo spazio di
     * quello che mostra. Vale anche per le radio: il «Preferito» di una riga
     * è una pillola sola.
     */
    public function pills(bool $pills = true): static
    {
        $this->schema['pills'] = $pills;

        return $this;
    }
}
