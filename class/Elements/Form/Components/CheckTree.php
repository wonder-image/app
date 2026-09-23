<?php

namespace Wonder\Elements\Form\Components;

/**
 * Variante "ad albero" di `CheckGroup`: renderizza una struttura
 * gerarchica `<ul><li>` compatibile con jsTree (`data-wi-tree="…"`).
 * Le option supportano `child` (sotto-opzioni) come per `CheckGroup`.
 */
class CheckTree extends CheckGroup
{
    /**
     * Il campo che tiene la voce principale fra quelle spuntate: l'albero
     * lo segna con una stella. Vedi `InputCheckTree::primaryField()`.
     */
    public function primaryField(string $name): self
    {
        return $this->schema('primary_field', $name);
    }
}
