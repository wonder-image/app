# Button con lightbox

```php
Button::make(__t('components.photos'))->lightbox('/photos/front.jpg');
Button::make(__t('components.photos'))->lightbox(['/photos/front.jpg', '/photos/interior.webp']);
Button::make(__t('components.tour'))->lightbox('https://tour.example/embed', type: 'iframe');
Button::make(__t('components.photos'))->lightbox('/image/123', type: 'image');
```

La firma è `lightbox(string|array $urls, ?string $type = null)`. Con type null, l'estensione del percorso identifica le immagini (anche con query string); gli altri URL sono iframe. Una lista può quindi contenere entrambi. Specificare type per endpoint immagine senza estensione. Una lista vuota disattiva Fancybox.

Il renderer condiviso registra la dipendenza Fancyapps e assegna un gruppo distinto a ogni bottone. Il primo URL è il link di fallback senza JavaScript. Le altre risorse sono link nascosti; nessun iframe viene creato prima dell'apertura. Html.autoSize è disattivato per iframe cross-origin. Il server esterno deve consentire l'incorporamento.

Funziona nei temi Wonder e Bootstrap, preservando etichetta, icona, classi e varianti del bottone. Un bottone disabled resta inattivo. Non combinare Fancybox con submit, reset o post: il renderer rifiuta l'abbinamento per evitare invii accidentali. I link accettano HTTP(S) o percorsi relativi.

Verifica: `php tests/button-lightbox.php`.
