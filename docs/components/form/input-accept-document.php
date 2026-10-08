<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputAcceptDocument;

return ComponentDoc::for(InputAcceptDocument::class)
    ->title('InputAcceptDocument')
    ->group('advanced')
    ->order(40)
    ->tags('consenso', 'privacy', 'termini', 'documento legale', 'checkbox')
    ->description('La spunta di accettazione di un documento legale (privacy, termini, cookie): una casella con l\'etichetta che linka il documento, e i dati del documento (`documentType()`, `documentId()`) che il salvataggio registra fra i consensi. `documentLabel()` scrive l\'etichetta in HTML quando serve il link al testo. Nelle Resource: `FormField::key(\'nome\')->acceptDocument(\'privacy\')`, che risolve il documento attivo dal database.')
    ->uses(FormField::class)
    ->docs('concetti/utenti/consensi.md', 'Registrazione consensi')
    ->docs('concetti/utenti/auth-frontend.md', 'Auth frontend')
    ->related('checkbox', 're-captcha')
    ->note('wonder', 'Esiste solo nel frontend, nei form di registrazione e contatto.')
    ->example('Base', <<<'PHP'
    (new InputAcceptDocument('privacy'))
        ->documentType('privacy')
        ->documentId(3)
        ->documentLabel('Ho letto la <a href="/privacy-policy/" target="_blank" rel="noopener noreferrer">Privacy Policy</a>')
        ->required()
    PHP)
    ->example('Termini e condizioni', <<<'PHP'
    (new InputAcceptDocument('terms'))
        ->documentType('terms_conditions')
        ->documentId(5)
        ->documentLabel('Accetto i <a href="/termini/" target="_blank" rel="noopener noreferrer">Termini e Condizioni</a>')
        ->value('true')
    PHP);
