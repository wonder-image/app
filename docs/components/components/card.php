<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Components\Text;
use Wonder\Elements\Form\Components\InputText;

return ComponentDoc::for(Card::class)
    ->title('Card')
    ->group('layout')
    ->order(10)
    ->tags('riquadro', 'contenitore', 'scheda', 'colonne')
    ->description('Il riquadro contenitore del backend: un `<div class="card">` con i figli in griglia. `columns()` dà le colonne della griglia interna, `columnSpan()` quante colonne occupa nel layout che la contiene, `gap()` lo spazio fra i figli. Sostituisce il vecchio `<wi-card>`.')
    ->uses(Button::class, SectionTitle::class, Text::class, InputText::class)
    ->docs('concetti/componenti/README.md#esempio-layout-di-un-form-con-card', 'Componenti UI')
    ->related('container', 'accordion', 'info-card')
    ->example('Base', <<<'PHP'
    (new Card())->components([
        SectionTitle::make('Dati principali')->level(5),
        Text::make('Un riquadro con un titolo, un testo e un bottone.'),
        Button::make('Salva')->variant('success'),
    ])
    PHP)
    ->example('Griglia a colonne con i campi', <<<'PHP'
    (new Card())->columns(2)->gap(3)->components([
        (new InputText('name'))->label('Nome'),
        (new InputText('surname'))->label('Cognome'),
        (new InputText('email'))->label('Email')->columnSpan(2),
    ])
    PHP, 'Nel `formLayoutSchema()` di una Resource i campi arrivano da `static::getInput(\'campo\')`; qui sono Element diretti per mostrare la griglia.')
    ->example('Visibile solo con un valore', <<<'PHP'
    (new Card())->visibleWhen('type', 'business')->components([
        Text::make('Questo riquadro compare quando il campo «type» vale «business».'),
    ])
    PHP, '`visibleWhen()` e `hiddenWhen()` scrivono gli attributi `data-visible-when*` letti dal JS del backend.');
