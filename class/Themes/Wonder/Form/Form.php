<?php

namespace Wonder\Themes\Wonder\Form;

use Wonder\Themes\Bootstrap\Concerns\{ HasColumns, HasGap };
use Wonder\Themes\Wonder\Component;

/**
 * Renderer del container `<form>` per il tema `Wonder` (frontend pubblico).
 *
 * Speculare a `Themes\Bootstrap\Form\Form`, ma con classe base
 * `wi-form` invece di un container Bootstrap. Riusa i trait
 * `HasColumns`/`HasGap` (anche se vivono nel namespace Bootstrap per
 * ragioni storiche, la logica è theme-agnostic: producono classi
 * grid che il CSS del frontend può mappare).
 *
 * Aggiunto in coppia con i renderer Wonder dei singoli campi:
 * permette di rendere un intero form (`Wonder\Elements\Form\Form`)
 * lato frontend pubblico senza dover passare per il tema Bootstrap.
 */
class Form extends Component
{
    use HasColumns;
    use HasGap;

    public function render($class): string
    {
        $this->propagateNoFloating($class);

        $columnsClass = $this->getColumns($class->columns ?? []);
        $gapClass = $this->getGap($class->gap ?? []);

        $cls = trim('wi-form '.$columnsClass.' '.$gapClass);

        $html = '<form action="" method="post" enctype="multipart/form-data" class="'.$cls.'">';
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
