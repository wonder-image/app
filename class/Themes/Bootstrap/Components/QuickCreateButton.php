<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Backend\Support\QuickCreateModal;
use Wonder\Elements\Components\Button;
use Wonder\Themes\Bootstrap\Component;

/**
 * Il bottone di creazione rapida staccato da un campo.
 *
 * È un `Button` secondario a contorno, come gli altri bottoni del backend,
 * con davanti il "+"; il modal e lo script sono quelli del "+" dei campi
 * (`QuickCreateModal`). Stringa vuota quando l'utente non può creare la
 * risorsa. Il bottone non è in fila con un campo: la colonna gliela dà il
 * layout della scheda (`ResourceFormLayoutRenderer`).
 */
class QuickCreateButton extends Component
{
    /** Numera i modal dei bottoni senza id, perché due bottoni della stessa risorsa non ne aprano uno solo. */
    private static int $sequence = 0;

    public function render($class): string
    {
        $config = $class->quickCreateConfig();
        $id = trim((string) ($class->getSchema('id') ?? ''));
        $seed = $id !== '' ? $id : 'button-'.$config['slug'].'-'.(++self::$sequence);

        // Nessun campo in cui far entrare la riga: `input` vuoto, e la
        // famiglia `button` dice a chi ascolta l'evento da dove arriva.
        $parts = QuickCreateModal::parts($config, '', 'button', QuickCreateModal::modalId($seed));

        if ($parts === null) {
            return '';
        }

        $button = Button::make($parts['button_label'])
            ->variant('secondary')
            ->outline()
            ->icon('bi bi-plus-lg', 'start')
            ->schema('inline', true);

        $size = (string) ($class->getSchema('size') ?? '');

        if ($size !== '') {
            $button->size($size);
        }

        $attributes = $class->getSchema('attributes');

        if (is_array($attributes)) {
            $button->attributes($attributes);
        }

        if ($id !== '') {
            $button->attr('id', $id);
        }

        $button->attributes($parts['trigger']);

        return $button->render('bootstrap').$parts['modal'].$parts['script'];
    }
}
