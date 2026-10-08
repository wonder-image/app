<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputUrl;

return ComponentDoc::for(InputUrl::class)
    ->title('Input URL')
    ->group('text')
    ->order(40)
    ->tags('url', 'link', 'sito', 'indirizzo', 'input')
    ->description('Il campo per un indirizzo web: un `InputText` con `type="url"`, che il browser accetta solo se assoluto e con lo schema (`https://...`) e per cui la tastiera del telefono mostra `/` e `.com`. Nei due temi rende come il campo di testo: `form-floating` nel backend, `wi-input-container url` nel frontend.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('input-text', 'input-email')
    ->example('Base', <<<'PHP'
    (new InputUrl('website'))
        ->label('Sito web')
    PHP)
    ->example('Valore e sola lettura', <<<'PHP'
    (new InputUrl('website'))
        ->label('Sito web')
        ->value('https://www.wonderimage.it')
        ->readonly()
    PHP, '`readonly()` lascia il valore selezionabile ma non modificabile; `disabled()` lo esclude anche dall\'invio del form.')
    ->example('Senza label galleggiante e con placeholder', <<<'PHP'
    (new InputUrl('website'))
        ->label('Sito web')
        ->placeholder('https://')
        ->noFloating()
    PHP, 'Con la label galleggiante il placeholder resterebbe coperto (Bootstrap) o sovrapposto (Wonder): `noFloating()` porta la label sopra il campo e lascia il posto al suggerimento.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('website')->url()->label('Sito web')
    PHP, '`url()` ritorna `Inputs\InputUrl`, che ha i soli modificatori universali.');
