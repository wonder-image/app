# Caricamento frontend e cache degli asset

In Herd, APP_URL deve corrispondere all'origine locale effettiva, ad esempio
`https://agliati.test`. Per upload assenti localmente, MEDIA_FALLBACK_URL puo
indicare il sito remoto: il driver risponde direttamente con un redirect 302,
senza restituire script PHP come file statici a Nginx.

Nei siti con inizializzazioni JavaScript compatibili con `DOMContentLoaded` o
con l'evento Wonder `loaded`, attivare in `custom/config/config.php`:

```php
use Wonder\App\Dependencies;
Dependencies::deferFrontend();
```

Gli script esterni registrati con Dependencies mantengono l'ordine e ricevono
`defer`, inclusi quelli di fine body. Il backend conserva il caricamento sincrono.
I CSS strutturali restano bloccanti per evitare layout incompleti al primo paint.
La traduzione viene inizializzata a DOMContentLoaded prima dei componenti;
`setUpPage` e l'evento `loaded` conservano il ciclo di vita su window.load.

La modalita legacy resta il default. Prima di attivare defer su un sito esistente,
spostare le chiamate inline immediate a `$`, `Swiper`, `Fancybox` o altri globali
in un listener DOMContentLoaded/loaded. Non aggiungere `async`: perderebbe
l'ordine fra dipendenze. `Dependencies::deferFrontend(false)` ripristina il default.

In modalita differita Moment non viene caricato implicitamente: SelectDate lo
registra quando serve. Codice personalizzato che lo usa deve richiedere
`Dependencies::moment()`. Swiper e Gallery del tema Wonder registrano le proprie
dipendenze; Fancyapps e richiesta solo per Gallery, zoom o lightbox. Renderizzare
i componenti prima dell'head, usando il buffering di View::layout.

Le immagini responsive emettono `?v=filemtime` per src e ogni variante srcset
esistente localmente. Anche `Image::url()` applica la stessa verifica al file
originale o alla variante selezionata con `size()`. Sostituendo un file allo
stesso percorso con una data di modifica diversa, cambia il parametro `v`;
la data viene riletta anche all'interno della stessa richiesta PHP.
File assenti, URL esterni o URL gia parametrizzati restano
invariati. Usare `Image::priority()` sulla hero visibile inizialmente, mai su tutte
le immagini: disabilita lazy loading e assegna fetchpriority high.

Il template Apache include il blocco `WONDER PERFORMANCE START/END`.
`php forge update --local` (oppure update in produzione) lo aggiorna automaticamente;
su installazioni legacy lo aggiunge in fondo senza cancellare personalizzazioni.
Le modifiche manuali vanno fuori dal blocco gestito. La cache degli asset statici
versionati dura un anno, quella degli URL non versionati un giorno con rivalidazione.
WebP e AVIF sono inclusi. HTML non riceve cache immutable.

Distribuzione: rilasciare wonder-image/app e la build npm di wonder-image/lib,
aggiornare le dipendenze del sito, eseguire npm install per copiare dist e
php forge update per applicare il blocco Apache. Una modifica locale a vendor
non sostituisce il rilascio dei pacchetti.
