<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\MediaPicker;

return ComponentDoc::for(MediaPicker::class)
    ->title('Media dalla libreria')
    ->group('file')
    ->order(20)
    ->tags('media', 'libreria', 'immagine', 'documento', 'video', 'icona', 'upload', 'picker')
    ->description('Il campo che lega un record a un file della libreria Media: la colonna tiene l\'**id** del media, non il percorso. Mostra l\'anteprima del media scelto (miniatura per immagini e icone, nome per documenti e video) e tre azioni: scegliere dalla libreria con la ricerca, caricare un file nuovo, togliere. Il file nuovo parte con il form e diventa un media quando si salva il record: nessun media resta orfano se il form viene abbandonato. Si dichiara con `FormField::key(\'image_id\')->mediaImage()` (o `mediaIcon()`, `mediaDocument()`, `mediaVideo()`); `mediaSlug(\'{slug}\')` e `mediaAlt(\'{name}\')` danno slug e testo alternativo al media creato dal campo, presi dai valori del record. Il tipo decide estensioni e peso massimo (`MediaTypes`).')
    ->uses(FormField::class, Assets::class)
    ->docs('concetti/media.md#il-campo-media-nei-form', 'Libreria Media: il campo nei form')
    ->docs('concetti/form/form-field.md#media-dalla-libreria', 'FormField: media dalla libreria')
    ->related('file', 'image', 'modal')
    ->note('bootstrap', 'Esiste solo nel backend, come il Repeater: `render(\'wonder\')` lancia un\'eccezione. L\'id sta in un input nascosto (`data-wi-media-value`), il file nuovo in `<campo>__wi_media_upload`. La finestra della libreria è un `Modal` solo per pagina; CSS e script escono una volta da `PageAssets` con il contratto `data-wi-media*`.')
    ->example(
        Example::make('Immagine già scelta')
            ->code(<<<'PHP'
            (new MediaPicker('image_id'))
                ->label('Immagine del prodotto')
                ->mediaType('image')
                ->value('12')
                ->media([
                    'id' => 12,
                    'name' => 'Paesaggio',
                    'thumbnail' => Assets::url('paesaggio-1.jpg'),
                ])
            PHP)
            ->description('`value()` è l\'id del media, `media()` i dati dell\'anteprima. Dal DSL delle Resource li riempie `InputMedia` leggendo la libreria (`MediaFile::find()`).')
            ->height(260)
    )
    ->example(
        Example::make('Campo vuoto, obbligatorio')
            ->code(<<<'PHP'
            (new MediaPicker('image_id'))
                ->label('Immagine di copertina')
                ->mediaType('image')
                ->required()
            PHP)
            ->description('Senza valore l\'anteprima dice che non c\'è nessun file e "Togli" è nascosto. Un campo obbligatorio vuoto ferma l\'invio del form.')
            ->height(240)
    )
    ->example(
        Example::make('Documento')
            ->code(<<<'PHP'
            (new MediaPicker('manual_id'))
                ->label('Manuale')
                ->mediaType('document')
                ->value('31')
                ->media(['id' => 31, 'name' => 'Manuale di montaggio'])
            PHP)
            ->description('Documenti e video non hanno miniatura: l\'anteprima mostra il nome e il segno del tipo. Le estensioni ammesse sono quelle di `MediaTypes`.')
            ->height(240)
    )
    ->example(
        Example::make('Media non più in libreria')
            ->code(<<<'PHP'
            (new MediaPicker('image_id'))
                ->label('Immagine')
                ->mediaType('image')
                ->value('99')
                ->error('Il media scelto non è più in libreria: scegline un altro.')
            PHP)
            ->description('Un id senza media resta nel campo e l\'anteprima lo dice: al salvataggio il server lo rifiuta con l\'errore sul campo, finché non si sceglie un altro media o lo si toglie.')
            ->height(280)
    )
    ->example(
        Example::make('Dal DSL delle Resource')
            ->code(<<<'PHP'
            FormField::key('image_id')
                ->mediaImage()
                ->mediaSlug('{slug}')
                ->mediaAlt('{name}')
                ->label('Immagine del prodotto')
            PHP)
            ->description('`mediaImage()` ritorna `Inputs\\InputMedia`. Il media creato caricando un file da questo campo prende come slug (e nome del file) lo slug del prodotto e come testo alternativo il suo nome.')
            ->height(240)
    );
