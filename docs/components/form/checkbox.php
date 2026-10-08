<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\Checkbox;

return ComponentDoc::for(Checkbox::class)
    ->title('Checkbox')
    ->group('choice')
    ->order(30)
    ->tags('checkbox', 'spunta', 'casella', 'booleano', 'consenso')
    ->description('La casella singola per un valore vero/falso. Un campo nascosto con lo stesso `name` precede la casella, così il form manda qualcosa anche quando è deselezionata; `checked()` la spunta e il valore postato è `true`. Nel backend è un `input-group` con `form-check-input` e la label resa come `form-control`; nel frontend il `wi-checkbox-container` della lib con l\'icona `bi-check-lg`. Nelle Resource si dichiara con `FormField::key(\'nome\')->checkbox()`, che con `options()` costruisce invece un `CheckGroup`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#scelta', 'FormField: scelta')
    ->related('check-group', 'toggle', 'check-boolean', 'input-accept-document')
    ->note('bootstrap', 'Il contenitore `wi-container-checkbox` prende `wi-checkbox-required` quando il campo è obbligatorio: è la classe che il controllo della lib legge.')
    ->note('wonder', 'La label porta già l\'asterisco dell\'obbligatorio; l\'errore esce nello `span.alert-error` sotto la lista.')
    ->example('Base', <<<'PHP'
    (new Checkbox('newsletter'))
        ->label('Iscrivimi alla newsletter')
    PHP)
    ->example('Spuntata e obbligatoria', <<<'PHP'
    [
        (new Checkbox('visible'))
            ->label('Visibile sul sito')
            ->checked(),
        (new Checkbox('terms'))
            ->label('Accetto le condizioni di vendita')
            ->required(),
    ]
    PHP, '`checked()` scrive l\'attributo nativo; `required()` aggiunge l\'asterisco alla label e la classe che il controllo del form legge.')
    ->example('Con errore di validazione', <<<'PHP'
    (new Checkbox('privacy'))
        ->label('Ho letto l\'informativa sulla privacy')
        ->required()
        ->error('Devi accettare l\'informativa per continuare.')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('featured')->checkbox()->label('In evidenza')->value('true')
    PHP, 'Lo stesso campo dichiarato come in `Resource::formSchema()`: un `value()` pari a `true`, `1` o `\'on\'` spunta la casella.');
