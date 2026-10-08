<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\File;

return ComponentDoc::for(File::class)
    ->title('File')
    ->group('file')
    ->order(10)
    ->tags('upload', 'file', 'immagini', 'filepond', 'drag and drop')
    ->description('Il campo di caricamento. `file()` sceglie il tipo accettato (`image`, `document`, ...), `maxFile()` quanti file, `maxSize()` il peso massimo in MB, `directory()` dove vanno; `mode(\'classic\')` è l\'`<input type="file">` nativo, il default è il drag and drop di FilePond con `uploader()` e `fileValue()` per i file già caricati. Nelle Resource: `FormField::key(\'nome\')->file()` e `->fileDragDrop()`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->related('image', 'gallery')
    ->note('bootstrap', 'Il drag and drop usa FilePond con `data-wi-file-references="true"`: la lib manda solo i file nuovi più il manifesto `<campo>__wi_files`, che `MediaFileManager::syncFiles()` confronta con i nomi vecchi.')
    ->note('wonder', 'Il frontend rende l\'input nativo vestito dalla lib, senza FilePond.')
    ->example('Base', <<<'PHP'
    (new File('cover'))
        ->label('Immagine di copertina')
        ->file('image')
        ->maxFile(1)
        ->maxSize(5)
    PHP)
    ->example(
        Example::make('Più file con drag and drop')
            ->code(<<<'PHP'
            (new File('gallery'))
                ->label('Galleria')
                ->file('image')
                ->maxFile(10)
                ->maxSize(8)
                ->directory('products')
                ->fileValue(['foto-1.jpg', 'foto-2.jpg'])
            PHP)
            ->description('`fileValue()` elenca i file già salvati: FilePond li mostra e il manifesto li conserva se non vengono tolti.')
            ->height(260)
    )
    ->example('Input nativo', <<<'PHP'
    (new File('contract'))
        ->label('Contratto firmato')
        ->file('document')
        ->mode('classic')
        ->extensionsAccept('.pdf')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('cover')->fileDragDrop('image')->label('Copertina')
    PHP);
