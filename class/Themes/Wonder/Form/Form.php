<?php

namespace Wonder\Themes\Wonder\Form;

use Wonder\Http\Csrf;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ResponsiveGridClasses;

/**
 * Renderer del container `<form>` per il tema `Wonder` (frontend pubblico).
 *
 * Speculare a `Themes\Bootstrap\Form\Form`, ma con classe base
 * `wi-form` invece di un container Bootstrap. Colonne e spazio escono
 * come utility della lib (`d-grid col-N gap-N`, con le varianti `-t-`
 * e `-p-` per tablet e telefono), le stesse del Container Wonder.
 *
 * Aggiunto in coppia con i renderer Wonder dei singoli campi:
 * permette di rendere un intero form (`Wonder\Elements\Form\Form`)
 * lato frontend pubblico senza dover passare per il tema Bootstrap.
 */
class Form extends Component
{
    use ResponsiveGridClasses;

    public function render($class): string
    {
        $this->propagateNoFloating($class);

        $cls = implode(' ', array_merge(
            ['wi-form', 'd-grid'],
            $this->responsiveClasses('col', $class->columns ?? []),
            $this->responsiveClasses('gap', $class->gap ?? [])
        ));

        $html = '<form action="" method="post" enctype="multipart/form-data" class="'.$cls.'">';
        $html .= Csrf::fieldFor('post');
        $html .= $this->renderComponents($class->components);
        $html .= '</form>';

        return $html;
    }

    /**
     * Propaga la flag `no_floating` dal Form ai child Component prima
     * del rendering, in modo che il default valga per tutti i campi.
     *
     * Override per singolo campo: se il child ha già `schema[no_floating]`
     * impostato esplicitamente (es. `noFloating(false)` per riattivare
     * il floating su un input specifico in un form noFloating), NON
     * viene sovrascritto. Quindi:
     *
     *   - Form senza `noFloating()`: nessun effetto.
     *   - Form `noFloating()` + child senza impostazione: child eredita true.
     *   - Form `noFloating()` + child `noFloating(false)`: child resta false.
     */
    private function propagateNoFloating(object $form): void
    {
        if (!array_key_exists('no_floating', $form->schema ?? [])) {
            return;
        }

        $value = (bool) $form->schema['no_floating'];

        foreach ($form->components ?? [] as $component) {
            if (!isset($component->schema) || !is_array($component->schema)) {
                continue;
            }

            if (array_key_exists('no_floating', $component->schema)) {
                continue;
            }

            $component->schema['no_floating'] = $value;
        }
    }
}
