<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\LegalDocument;

return ComponentDoc::for(LegalDocument::class)
    ->title('LegalDocument')
    ->group('content')
    ->order(65)
    ->tags('privacy', 'cookie', 'termini', 'legale', 'consensi', 'traduzioni')
    ->description('Il documento legale attivo di un tipo (`privacy_policy`, `cookie_policy`, `terms_conditions`, ...) letto da `legal_documents`, la stessa fonte delle versioni che l\'utente accetta. `make($type)` sceglie la lingua corrente e, se manca, quella di default; i `{{placeholder}}` sono risolti come in `infoLegalDocument()`. Il contenuto è l\'HTML dei blocchi dell\'editor (`EditorBlocksRenderer`). `title(false)` nasconde il nome, `title(true, \'h2\')` cambia il tag. `document($row)` passa una riga già letta, `LegalDocument::resolveUsing()` sostituisce la lettura. Senza documento non stampa nulla.')
    ->docs('concetti/traduzioni.md#documenti-legali', 'Traduzioni')
    ->related('rich-text')
    ->note('wonder', 'Titolo con la classe `title` della lib e contenuto in `.text`, come le pagine legali del sito.')
    ->note('bootstrap', 'Titolo `h3` e contenuto senza classi aggiunte: utile per un\'anteprima nel backend.')
    ->example('Da una riga di legal_documents', <<<'PHP'
    LegalDocument::make('privacy_policy')->document([
        'name' => 'Privacy policy',
        'version' => '1.0.0',
        'language_code' => 'it',
        'content_snapshot' => json_encode(['blocks' => [
            ['type' => 'header', 'data' => ['text' => 'Titolare del trattamento', 'level' => 4]],
            ['type' => 'paragraph', 'data' => ['text' => 'Il titolare del trattamento è <b>Esempio S.r.l.</b>']],
            ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Dati di navigazione', 'Dati forniti dall\'utente']]],
        ]]),
    ])
    PHP, 'Nel sito basta `LegalDocument::make(\'privacy_policy\')`: la riga attiva si legge dal database.')
    ->example('Senza titolo', <<<'PHP'
    LegalDocument::make('cookie_policy')->title(false)->document([
        'name' => 'Cookie policy',
        'content_snapshot' => ['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Questo sito usa solo cookie tecnici.']],
        ]],
    ])
    PHP);
