<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\TextareaEditor;

return ComponentDoc::for(TextareaEditor::class)
    ->title('Editor di testo')
    ->group('text')
    ->order(120)
    ->tags('editor', 'rich text', 'html', 'quill', 'editorjs', 'blocchi', 'textarea')
    ->description('L\'editor di testo formattato del backend: un `<textarea>` nascosto con il valore in base64 in `data-wi-value`, accanto al quale la lib costruisce l\'editor (`setTextarea()`). `version()` sceglie il preset: `base`, `plus` e `pro` sono Quill con barre via via più ricche, `blog` e `table` sono Editor.js a blocchi e vogliono come valore il JSON dei blocchi; `folder()` è la cartella in cui l\'editor carica immagini e file. Nelle Resource si dichiara con `textarea(\'plus\')` e sul Model il campo è `richText()`, così l\'HTML passa dal `sanitize`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#area-di-testo', 'FormField: area di testo')
    ->docs('concetti/risorse/database.md#testo-formattato-richtext', 'Model: testo formattato (richText)')
    ->related('textarea')
    ->unsupported('wonder', 'Il renderer emette lo stesso markup del backend, ma la lib frontend non inizializza `data-wi-textarea`: il textarea resta `d-none` e nella pagina non compare nulla. Nel frontend usa `Textarea`.')
    ->note('bootstrap', 'La label è un `h6` dentro il `form-floating`; l\'editor compare dopo il container, nel DOM, quando la lib legge `data-wi-textarea`. I preset Quill (`base`, `plus`, `pro`) accettano HTML, quelli Editor.js (`blog`, `table`) il JSON dei blocchi.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new TextareaEditor('body'))
                ->label('Testo')
                ->version('plus')
                ->value('<p>Benvenuti nel <strong>nuovo sito</strong>: qui raccontiamo chi siamo e cosa facciamo.</p>')
            PHP)
            ->description('`plus` è il preset Quill più usato; il valore HTML arriva all\'editor codificato in base64.')
            ->height(320)
    )
    ->example(
        Example::make('Barra ridotta')
            ->code(<<<'PHP'
            (new TextareaEditor('note'))
                ->label('Nota interna')
                ->version('base')
                ->folder('note')
            PHP)
            ->description('`base` ha i soli strumenti essenziali; `folder()` indica dove finiscono i file caricati dall\'editor.')
            ->height(280)
    )
    ->example(
        Example::make('Editor a blocchi')
            ->code(<<<'PHP'
            (new TextareaEditor('article'))
                ->label('Articolo')
                ->version('blog')
            PHP)
            ->description('`blog` e `table` montano Editor.js: senza valore partono vuoti, altrimenti vogliono l\'array JSON dei blocchi salvato dall\'editor stesso.')
            ->height(360)
    )
    ->example(
        Example::make('Dal DSL delle Resource')
            ->code(<<<'PHP'
            FormField::key('body')->textarea('plus')->label('Testo')
            PHP)
            ->description('`textarea()` con una versione ritorna `Inputs\InputTextarea`, che costruisce il `TextareaEditor` e gli passa la cartella upload del contenuto corrente.')
            ->height(320)
    );
