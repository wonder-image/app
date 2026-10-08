<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\GoogleAddress;

return ComponentDoc::for(GoogleAddress::class)
    ->title('GoogleAddress')
    ->group('advanced')
    ->order(30)
    ->tags('indirizzo', 'google places', 'autocomplete', 'geocoding')
    ->description('Un campo indirizzo con il completamento di Google Places: scelto un risultato, i pezzi (`country`, `province`, `city`, `cap`, `street`, `number`, coordinate) finiscono nei campi nascosti con lo stesso nome o, con `alias()`, con il prefisso `<alias>_`. `restriction()` limita i Paesi, `breakdown()` precompila i pezzi. La chiave API viene da `Credentials::api()` del sito. Nelle Resource: `FormField::key(\'nome\')->googleAddress([...])`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->docs('servizi/google-maps.md', 'Google Maps')
    ->related('google-map', 'input-text')
    ->note('wonder', 'Esiste solo nel frontend; senza chiave API il campo resta un testo semplice.')
    ->example('Base', <<<'PHP'
    (new GoogleAddress('address'))
        ->label('Indirizzo')
        ->restriction(['it'])
    PHP)
    ->example('Con alias e pezzi precompilati', <<<'PHP'
    (new GoogleAddress('shipping'))
        ->label('Indirizzo di spedizione')
        ->alias('shipping')
        ->restriction(['it', 'ch'])
        ->breakdown(['city' => 'Milano', 'cap' => '20121', 'street' => 'Via Dante', 'number' => '1'])
    PHP, 'I campi nascosti diventano `shipping_city`, `shipping_cap`, e così via: più indirizzi nello stesso form.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('address')->googleAddress(['it'], 'billing')->label('Indirizzo di fatturazione')
    PHP);
