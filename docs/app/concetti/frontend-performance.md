# Caricamento frontend e cache degli asset

## CSS piccoli e font

Sul frontend `Dependencies` inlina automaticamente `wi-lib`, il CSS di Swiper e
il foglio strutturale `wi-frontend`. `wi-lib` e Swiper hanno un limite di 32 KiB;
`wi-frontend` ha un limite esplicito di 256 KiB e risolve gli URL relativi degli
asset rispetto al percorso originale del foglio. `@import`, contenuti non adatti
a un tag style e file oltre il rispettivo limite mantengono il link esterno.
`inlineFrontendStyles()` resta disponibile per sostituire esplicitamente
l'elenco, ma i siti non devono configurare questi fogli.

Questa scelta elimina dal percorso critico le richieste separate per il CSS del
framework e di Swiper. Aumenta il peso dell'HTML e rinuncia alla cache separata di
quei fogli; i limiti impediscono che la crescita del bundle aumenti il documento
senza controllo.

I Google Fonts configurati in `css_font` ricevono `display=swap` e vengono
caricati come stylesheet asincroni con fallback `noscript`. I link verso altri
provider restano stylesheet normali, perche il framework non puo presumerne il
comportamento. I siti che distribuiscono font locali devono includere anche la
relativa licenza.

Le immagini locali raster ricevono width e height intrinseci se entrambi gli
attributi sono assenti. Nessun download viene effettuato per URL esterni o file
assenti; le dimensioni specificate dal progetto restano prioritarie.

## reCAPTCHA

La lib inizializza il widget quando arriva entro 300px dal viewport oppure al
focus/pointerdown sul form. Il loader e unico e ogni widget viene renderizzato
una sola volta. Il form mantiene i campi token/action richiesti e la verifica
server; scadenza ed errore azzerano il token. Dopo un errore di caricamento,
una nuova interazione consente di riprovare.

Il report Lighthouse puo elencare lo stesso script Google in piu iframe:
questo, da solo, non dimostra tre inserimenti del loader nel documento padre.
Controllare separatamente script della pagina, widget e iframe Google.
La scelta del caricamento ritardato riduce il contesto osservato da reCAPTCHA
prima dell'interazione; monitorare gli esiti antispam dopo il rilascio.
Riferimento: https://developers.google.com/recaptcha/docs/loading

In Herd, APP_URL deve corrispondere all'origine locale effettiva, ad esempio
`https://agliati.test`. Per upload assenti localmente, MEDIA_FALLBACK_URL puo
indicare il sito remoto: il driver risponde direttamente con un redirect 302,
senza restituire script PHP come file statici a Nginx.

Gli script esterni registrati con Dependencies mantengono l'ordine e ricevono
automaticamente `defer` sul frontend, inclusi quelli di fine body. Il backend
conserva il caricamento sincrono.
Il CSS strutturale resta disponibile prima del primo paint perché viene inserito
direttamente nell'head.
La traduzione viene inizializzata a DOMContentLoaded prima dei componenti;
`setUpPage` e l'evento `loaded` conservano il ciclo di vita su window.load.

Le chiamate inline a `$`, `Swiper`, `Fancybox` o altri globali devono partire da
`DOMContentLoaded` o dall'evento `loaded`. Non aggiungere `async`: perderebbe
l'ordine fra dipendenze. Solo un sito legacy non ancora migrato puo usare
temporaneamente `Dependencies::deferFrontend(false)`.

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
