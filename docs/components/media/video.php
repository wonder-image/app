<?php

use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Media\Video;

return ComponentDoc::for(Video::class)
    ->title('Video')
    ->order(20)
    ->tags('video', 'mp4', 'webm', 'poster', 'autoplay')
    ->description('Un video HTML5 con poster, sorgente WebM opzionale e avvio configurabile: `autoplay()` (muto e in linea, come richiedono i browser), `hover()` parte al passaggio del mouse, `start()` sceglie la modalità. `fixed()` lo rende un filtro a schermo intero, `filter()` aggiunge il velo. I percorsi degli esempi sono indicativi: il catalogo non ha un file video.')
    ->uses(Assets::class)
    ->docs('concetti/componenti/video-e-iframe.md', 'Video e Iframe')
    ->related('image', 'iframe')
    ->example('Base', <<<'PHP'
    Video::src('/assets/upload/video/presentazione.mp4')
        ->poster(Assets::url('paesaggio-2.jpg'))
        ->controls()
        ->addClass('w-100 rounded')
    PHP, 'Con `poster()` l\'utente vede un\'immagine finché non avvia il video.')
    ->example('Autoplay in loop e WebM', <<<'PHP'
    Video::src('/assets/upload/video/sfondo.mp4')
        ->webm()
        ->poster(Assets::url('paesaggio-3.jpg'))
        ->autoplay()
        ->loop()
        ->muted()
        ->playsInline()
        ->addClass('w-100')
    PHP, '`webm()` aggiunge la `<source>` WebM accanto all\'MP4 (stesso nome, estensione diversa, o un percorso esplicito).')
    ->example('Al passaggio del mouse', <<<'PHP'
    Video::src('/assets/upload/video/anteprima.mp4')
        ->poster(Assets::url('paesaggio-1.jpg'))
        ->hover()
        ->addClass('w-100')
    PHP, '`hover()` è `start(\'hover\')`: parte quando il puntatore entra e si ferma quando esce.');
