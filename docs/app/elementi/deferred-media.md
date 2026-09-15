# Contenuti caricati su richiesta

`Iframe::deferred()` mantiene l'iframe in un template inerte fino al clic. Il comportamento JavaScript appartiene a `wonder-image/lib` (`DeferredContent`); il framework gestisce configurazione e markup nei temi Wonder e Bootstrap.

```php
use Wonder\Elements\Media\Iframe;
use Wonder\Elements\Components\Button;

echo Iframe::url($url)
    ->attr('title', __t('pages.video.title'))
    ->deferred(button: Button::make(__t('pages.video.open'))
        ->variant('secondary')->outline()->icon('bi bi-play-fill'));
```

Il pulsante e un normale `Button`: testo, icona, variante, dimensione e classi restano personalizzabili. `deferredButton($button)` permette di impostarlo separatamente. Il renderer clona il pulsante, aggiunge il contratto JS e collega il fallback all'URL dell'iframe, senza modificare l'oggetto del chiamante. Il testo predefinito usa `components.media.load_content`.

## Modalita

- `deferred()` o `deferred('interaction')`: attivazione al clic o da tastiera.
- `deferred('visible')`: attivazione quando il contenitore entra nel viewport; rimane il pulsante come alternativa.
- `deferred(false)`: normale iframe, invariato rispetto al comportamento precedente.
- `expandable()->deferred()` conserva il lightbox anche quando l'attivazione avviene dopo gli eventi di caricamento della pagina.

## Dimensioni senza altezza obbligatoria

Il rapporto predefinito e 16:9. `ratio('4:3')` lo personalizza; attributi numerici `width` e `height` permettono di ricavarlo. Stili espliciti di dimensionamento sono rispettati. Con `fitCover()` o `fitContain()` e senza rapporto esplicito, il wrapper riempie il genitore gia dimensionato e posizionato.

Lo spazio e riservato prima dell'attivazione e si adatta al resize tramite CSS: non serve misurarlo con JavaScript. Non si tenta di leggere l'altezza del documento di iframe cross-origin. Se occorre dimensionamento basato sul contenuto remoto, serve una collaborazione esplicita del provider, per esempio un protocollo `postMessage` verificato.

## Altri contenuti

```php
use Wonder\Elements\Media\Deferred;

echo Deferred::make($map)
    ->fallbackUrl($externalUrl)
    ->button(Button::make(__t('pages.map.open')))
    ->ratio('3:1');
```

`make()` accetta un elemento renderizzabile oppure HTML fidato generato sul server, mai HTML libero fornito dall'utente. `fill()` riempie un genitore posizionato e dimensionato; `mode('visible')` attiva il caricamento nel viewport. Si possono comporre nel contenuto anche gli asset necessari al widget: gli script restano inerti e vengono attivati in ordine.

Gli script di contenuti differiti devono inizializzare subito se DOMContentLoaded/load sono gia avvenuti. `wi:deferred:ready` indica che il template e stato montato e i suoi script attivati, non certifica il caricamento interno dei provider. Errori di caricamento degli script emettono `wi:deferred:error` e mantengono utilizzabile il collegamento alternativo.

Un fallback senza JavaScript deve essere visibile: se il contenuto e dentro un accordion dipendente da JS, aggiungere anche un collegamento esterno fuori dall'accordion in un `<noscript>`.

## Verifica e distribuzione

`php tests/deferred-media.php` verifica entrambi i temi. I test browser di lib sono in `test/deferred-content.browser.test.cjs`.

Distribuire insieme l'app aggiornata e i bundle frontend/backend di lib che espongono `DeferredContent` e il CSS `wi-deferred`. Aggiornare le dipendenze e rigenerare gli asset del sito tramite npm; non copiare implementazioni JS nei moduli e non modificare vendor a mano.
