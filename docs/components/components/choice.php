<?php

use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Choice;
use Wonder\Elements\Components\Container;

return ComponentDoc::for(Choice::class)
    ->title('Choice')
    ->group('choice')
    ->order(10)
    ->tags('radio', 'checkbox', 'scelta', 'riquadro', 'checkout')
    ->description('Un radio o un checkbox in un riquadro cliccabile, con titolo, testo e una colonna a lato per il prezzo. Titolo, testo e aside sono escapati; le parti vuote restano nel markup con `hidden`, così un `<template>` reso da un `Choice` ha sempre tutte le parti e il JS le riempie.')
    ->uses(Container::class, Assets::class)
    ->docs('concetti/componenti/README.md#choice-choicegroup-e-steps', 'Componenti UI')
    ->related('choice-group', 'steps', 'checkbox')
    ->note('wonder', 'Usa `.wi-choice` della lib; `panel()` è visibile solo quando l\'input è scelto (`:has(input:checked)`).')
    ->note('bootstrap', 'Rende un `form-check` dentro una `card`; il pannello è una `card-footer` sempre visibile.')
    ->example('Base', <<<'PHP'
    Choice::make('shipping_method_id', 1)
        ->title('Corriere espresso')
        ->text('Consegna in 2-3 giorni lavorativi')
        ->aside('4,90 €')
        ->checked()
    PHP)
    ->example('Checkbox, icona e disabilitato', <<<'PHP'
    (new Container())->gap(2)->components([
        Choice::make('extras[]', 'gift')->type('checkbox')->title('Confezione regalo')->aside('2,00 €')->icon('gift'),
        Choice::make('extras[]', 'insurance')->type('checkbox')->title('Assicurazione')->text('Copre smarrimento e danni')->aside('1,50 €')->icon('bi-shield-check'),
        Choice::make('extras[]', 'express')->type('checkbox')->title('Non disponibile')->disabled(),
    ])
    PHP, '`icon()` accetta un nome di Bootstrap Icons con o senza il prefisso `bi-`.')
    ->example('Loghi e pannello', <<<'PHP'
    Choice::make('payment_method_id', 1)
        ->title('Carta di credito')
        ->icons([
            ['src' => Assets::url('quadrato-1.svg'), 'alt' => 'Visa'],
            ['src' => Assets::url('quadrato-2.svg'), 'alt' => 'Mastercard'],
            ['src' => Assets::url('ritratto-1.svg'), 'alt' => 'Amex'],
            ['src' => Assets::url('paesaggio-1.svg'), 'alt' => 'Altro'],
        ], 3)
        ->panel('Dopo aver cliccato «Paga ora» verrai reindirizzato al circuito.')
        ->checked()
    PHP, '`icons()` mostra i loghi a destra e oltre il massimo scrive «+N»; `panel()` aggiunge un riquadro sotto la scelta.');
