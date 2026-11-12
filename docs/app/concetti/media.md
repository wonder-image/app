# Libreria Media

La libreria **Media** è il posto unico dei file pubblici caricati dal backend:
immagini, icone, documenti e video. Un record che usa uno di questi file ne
salva l'**id**, non il percorso: se il file cambia in libreria, cambia ovunque
è usato.

Restano fuori i file privati (fatture, allegati degli ordini, documenti dei
clienti, foto profilo): la libreria è condivisa e pubblica, quelli sono campi
file normali.

Questa pagina descrive i tipi, lo slug stabile, il campo dei form che sceglie
o carica un media, la lettura dal sito, il registro degli utilizzi e la
migrazione delle icone.

## Tipi

Tutti i media stanno nella tabella `media` (`Wonder\App\Models\Media\Media`),
divisi dalla colonna `type`. Ogni tipo ha la sua sezione nel menu Media e la
sua Resource; le regole stanno in un posto solo,
`Wonder\App\Media\MediaTypes`.

| Tipo | Resource | Estensioni | Cartella in `assets/upload` | Peso massimo | Misure |
|---|---|---|---|---|---|
| `image` | `ImageResource` | png, jpg, jpeg, webp | `images` | 5 MB | responsive e webp |
| `icon` | `IconResource` | svg | `icons` | 5 MB | nessuna |
| `document` | `DocumentResource` | pdf, doc, docx, xls, xlsx, ppt, pptx, odt, ods | `documents` | 5 MB | nessuna |
| `video` | `VideoResource` | mp4, webm, mov | `videos` | 100 MB | nessuna |

- **Icone solo vettoriali.** Un svg passa da
  `Wonder\App\Security\SvgSanitizer` prima di essere salvato (vedi
  [Sicurezza](../piattaforma/sicurezza.md#svg)). Un'icona raster è
  un'immagine: va caricata fra le immagini.
- **Video come sono.** Niente misure e niente miniature. Il peso è limitato
  anche da `upload_max_filesize` e `post_max_size` di PHP. Per questo i video
  non passano dall'upload di massa: si caricano uno alla volta dalla loro
  sezione.
- **Upload di massa.** Accetta immagini, icone svg e documenti; il tipo scelto
  decide quale Resource valida e salva il file.

```php
use Wonder\App\Media\MediaTypes;

MediaTypes::all();                       // ['image', 'icon', 'document', 'video']
MediaTypes::extensions(MediaTypes::ICON); // ['svg']
MediaTypes::folder(MediaTypes::VIDEO);    // 'videos'
MediaTypes::accepts(MediaTypes::IMAGE, 'foto.jpg'); // true
```

Le quattro Resource estendono `MediaResource`, che contiene form, elenco, API
e permessi comuni. Un tipo dichiara solo percorso, testi, voce di menu e
`mediaType()`.

Per i form fuori dalla libreria, `fileDragDrop()` conosce tre categorie in
più: `svg`, `document` e `media-video` (mp4, webm, mov). La categoria `video`
resta solo mp4.

Immagini e icone hanno il **testo alternativo** (`alt`) nella scheda e nelle
API generate; documenti e video no.

## Slug e nome del file stabili

Lo slug di un media nasce con lui e **non cambia più**. Il file si chiama
come lo slug (`assets/upload/images/<slug>.<estensione>`), quindi anche il suo
indirizzo resta lo stesso quando:

- si sostituisce il file dalla scheda del media o dal campo di un record;
- si cambia il nome del media in libreria.

Motori di ricerca, feed e link esterni continuano a funzionare; la cache del
browser la rinnova già `Asset::version()` con `?v=<data di modifica>`. Se il
file nuovo ha un'altra estensione (`.jpg` → `.png`) il nome base resta e
cambia solo l'estensione.

Lo slug è unico in tutta la tabella. Viene dal nome dato al media al momento
della creazione, oppure dal modello del campo che lo crea (vedi sotto).

{% hint style="info" %}
Rinominare un file su richiesta, con un reindirizzamento dal vecchio
indirizzo, non esiste: è un lavoro a parte.
{% endhint %}

### Niente misure della foto di prima

Con il nome che resta, il file nuovo arriva dove c'era quello vecchio. Prima
di scriverlo `uploadFiles()` toglie le misure (`<slug>-<larghezza>`) e il
webp che trova su quel nome (`MediaFileManager::deleteDerived()`): finché la
[coda dei ridimensionamenti](coda-ridimensionamenti.md) non le rigenera, il
sito mostra l'originale nuovo, mai una misura della foto di prima. Quando
cambia l'estensione, il file vecchio se ne va con le misure della sua
estensione e i `.webp` restano quelli del file nuovo
(`MediaFileManager::deleteReplacedFiles()`).

La scheda di un'immagine avvisa in alto quando le misure sono ancora in
preparazione o non si sono generate (`MediaResource::resizeNotices()`, da
`ResizeQueue::status()` ed `error()`).

## Il campo media nei form

Un record sceglie un media con un campo dedicato: la colonna tiene l'id.

```php
use Wonder\App\ResourceSchema\FormField;

FormField::key('image_id')->mediaImage()
    ->mediaSlug('{slug}')   // slug e nome del file del media creato dal campo
    ->mediaAlt('{name}');   // il suo testo alternativo

FormField::key('icon_id')->mediaIcon();
FormField::key('manual_id')->mediaDocument();
FormField::key('spot_id')->mediaVideo();
```

I quattro type-helper sono `media($tipo)` con un tipo di `MediaTypes`: da lì
arrivano estensioni e peso massimo. Nel Model la colonna è un intero che può
essere vuoto:

```php
// tableSchema()
Column::key('image_id')->int()->null(),

// dataSchema()
Field::key('image_id')->number(),
```

Nel form il campo mostra l'anteprima del media scelto (la miniatura per
immagini e icone, il nome per documenti e video) e tre azioni: **Scegli dalla
libreria** (una finestra con la ricerca), **Carica un file**, **Togli**.

Il campo esiste solo nel backend (tema Bootstrap), come il Repeater: nel tema
Wonder `render()` lancia un'eccezione. Non è previsto dentro le righe di un
repeater. I campi file esistenti non cambiano da soli: restano `file()` e
`fileDragDrop()` finché non li si sostituisce.

### Cosa succede al salvataggio

La scelta si risolve sul server quando si salva il record, non con un upload
a parte: un form abbandonato non lascia media orfani. Il campo invia l'id del
media scelto, oppure un file, oppure niente (campo svuotato). Lo legge
`Wonder\App\Media\MediaInputs`, che i controller delle Resource chiamano dopo
`mutateRequestValues()` e prima della preparazione dei valori, sia dalla
pagina del backend sia dalle API generate:

| Arriva | Succede |
|---|---|
| l'id di un media | deve esistere, non essere eliminato ed essere del tipo del campo; altrimenti errore sul campo |
| un file, e il campo era vuoto | nasce un media del tipo del campo; la colonna prende il suo id |
| un file, e il media di prima è usato **solo** da questa colonna di questo record | il file del media viene sostituito: id, slug e testo alternativo restano |
| un file, e il media di prima è usato anche altrove | nasce un media nuovo e il record punta a quello; il media condiviso non si tocca |
| il campo vuoto | la colonna si svuota |
| niente (il campo non è nella richiesta) | il record tiene il suo valore |

"Usato solo da questo record" lo dice il [registro degli
utilizzi](#registro-degli-utilizzi); se il registro non risponde vale la
strada prudente, un media nuovo.

Il media nasce e si sostituisce con la stessa strada della sua sezione in
libreria (`mutateRequestValues()` e `prepareFor()` della Resource del tipo,
quindi `uploadFiles()`): stessi controlli su estensione, contenuto e peso,
svg ripulito, misure in coda.

Un file rifiutato, un id sconosciuto o del tipo sbagliato **fermano il
salvataggio** con il motivo sotto il campo; nella colonna non entra mai un id
non valido. Dalle API la risposta è 422 con `errors.<campo>`.

Nel form il file viaggia nel campo `<campo>__wi_media_upload`. Dalle API si
manda `image_id` con l'id, oppure un file multipart con lo stesso nome della
colonna (che deve essere fra i campi scrivibili di `apiSchema()`).

{% hint style="info" %}
Il media viene creato prima che il record sia validato. Se poi il record non
si salva per un altro errore, il media resta in libreria come "non usato" e
il form lo ripropone già scelto: non serve ricaricare il file.
{% endhint %}

### Slug dal record

`mediaSlug('{slug}')` dà al media creato dal campo lo slug, e quindi il nome
del file, presi dai valori del record: l'immagine del prodotto `scarpa-rossa`
si chiama `scarpa-rossa.jpg`. Tra graffe va il nome di una colonna del
record; si possono comporre (`{slug}-retro`).

- Vale **solo alla creazione** del media dal campo. Uno slug non segue mai il
  record: se il prodotto cambia slug, il media tiene il suo.
- Se lo slug è già di un altro media prende `-2`, `-3`, ...
- Senza modello lo slug viene dal nome del file caricato, come in libreria.
- I valori sono quelli della richiesta, prima della formattazione del
  record; una colonna che non è nella richiesta vale quella salvata.

### Testo alternativo dal record

`mediaAlt('{name}')` dà il testo alternativo al media creato dal campo.

- Alla sostituzione del file il testo alternativo resta.
- Quando il record viene salvato con valori diversi (prodotto rinominato) il
  testo alternativo lo **segue solo se nessuno lo ha scritto a mano**: se è
  ancora quello che il modello dava con i valori di prima, diventa quello dei
  valori nuovi. Non serve una colonna in più: si confronta il testo salvato
  con il modello riempito dai valori precedenti.
- Un testo alternativo corretto a mano in libreria non viene più toccato.

### Registro aggiornato da solo

Dopo un salvataggio riuscito i controller chiamano `MediaUsages::sync()` per
ogni campo media del form; quando il record viene eliminato (pagina, API
generate, bottone "Elimina" dell'elenco) chiamano `MediaUsages::forget()`.
Chi usa i campi media non deve fare niente. Una Resource senza campi media
non fa nessuna query in più.

Chi salva un record fuori dai controller delle Resource fa lo stesso a mano:

```php
$media = ProductResource::mediaInputs();
$values = $media->resolve($values, $oldValues);   // prima di preparare i valori

if ($media->errors() !== []) {
    // campo => motivo: non salvare
}

// ... salvataggio riuscito ...
$media->sync($id, $values);

// ... record eliminato ...
ProductResource::mediaInputs()->forget($id);
```

### La ricerca della finestra

La finestra legge `POST /api/backend/media/search/` (rotta
`api.backend.media.search`, protetta, con token CSRF): parametri `type` (uno
di `MediaTypes`, altrimenti 422), `term` e `page`. Ogni utente del backend può
chiamarla, perché chi modifica un record deve poter scegliere un media.
Risponde con 24 media per pagina e solo `id`, `name`, `slug`, `alt`, `type` e
`thumbnail` dei media non eliminati di quel tipo
(`MediaLibrary::search()`).

CSS e script del campo escono una volta per pagina da
`Themes\Support\PageAssets`, con il contratto `data-wi-media*`
(`data-wi-media` sulla radice con il tipo, `-value`, `-upload`, `-choose`,
`-remove`, `-preview`, `-search`), che `wonder-image/lib` può prendere in
carico senza cambiare il markup.

## Leggere un media nel sito

`Wonder\App\Media\MediaFile` legge un media dal suo id:

```php
use Wonder\App\Media\MediaFile;
use Wonder\Elements\Media\Image;

$cover = MediaFile::find($product['image_id']);   // null se non c'è

$cover?->url();       // indirizzo con la versione
$cover?->alt;         // testo semplice
$cover?->type;        // 'image'
$cover?->fileName();  // 'scarpa-rossa.jpg'

// Più record: una lettura sola.
$covers = MediaFile::many(array_column($products, 'image_id'));
$covers[$product['image_id']] ?? null;

// Immagine responsive, con il testo alternativo e le misure del sito.
echo Image::fromMedia($product['image_id'])->displaySizes('(min-width: 768px) 50vw, 100vw');
echo Image::fromMedia($covers[$product['image_id']] ?? null);

// Documenti e video: l'indirizzo.
echo MediaFile::find($product['manual_id'])?->url();
```

Un id vuoto, sconosciuto o di un media eliminato dà `null`, e
`Image::fromMedia()` non stampa niente: una pagina pubblica non si rompe per
un media tolto. `alt` e `name` sono testo semplice, da stampare con `e()`.

## Registro degli utilizzi

`Wonder\App\Media\MediaUsages` tiene traccia di chi usa quale media, nella
tabella `media_usages` (`media_id`, `owner_table`, `owner_id`,
`owner_column`). La tabella è creata da `forge update` e resta fuori dalla
sincronizzazione.

Chi salva l'id di un media in un record lo dichiara:

```php
use Wonder\App\Media\MediaUsages;

// Dopo il salvataggio: i media usati da quella colonna diventano questi.
MediaUsages::sync('gst_category', $categoryId, 'image_id', [$mediaId]);

// Colonna svuotata.
MediaUsages::sync('gst_category', $categoryId, 'image_id', []);

// Record eliminato: non usa più niente.
MediaUsages::forget('gst_category', $categoryId);
```

`sync()` sostituisce: i media che la colonna usava prima e che non sono più
nell'elenco tornano liberi. Gli id non validi e i doppioni si scartano.

Per leggere:

```php
MediaUsages::inUse($mediaId);   // bool
MediaUsages::of($mediaId);      // dove è usato
MediaUsages::message($mediaId); // "In uso in: categoria «Estate» (campo Immagine)."
```

Ogni riga di `of()` ha `table`, `id`, `column` e la descrizione per le
persone: `label` (il nome della Resource della tabella, trovata con
`ResourceRegistry::resolveByTable()`), `name` (il nome del record, dalle
colonne `name`, `title`, `label`, `code`, `slug`), `field` (l'etichetta del
campo in `labelSchema()`), `url` (la pagina di modifica) e `resource`. Senza
Resource restano la tabella e `#id`.

Il registro non fa query finché non viene chiamato: un sito che non usa i
media per id non paga niente.

{% hint style="warning" %}
Il registro sa solo quello che gli viene detto. Un record che smette di usare
un media senza `sync()` o `forget()` lo lascia bloccato. I [campi
media](#il-campo-media-nei-form) delle Resource lo aggiornano da soli; chi
salva l'id di un media in un altro modo (una colonna scritta a mano, una riga
di un repeater) chiama `sync()` dopo il salvataggio e `forget()` nel suo
`afterDelete()` o dove gestisce l'eliminazione.
{% endhint %}

### Nei test

Lo store è sostituibile, come per la coda delle email:

```php
use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\MemoryMediaUsageStore;

MediaUsages::useStore(new MemoryMediaUsageStore());
MediaUsages::describeUsing(static fn (array $owner): array => ['label' => 'categoria', 'name' => 'Estate']);
```

`useStore(null)` e `describeUsing(null)` tornano al database e alle Resource.

## Eliminazione bloccata

Un media in uso non si elimina. `MediaResource::assertDeletable()` lancia
`RuntimeException` con il messaggio che dice dove è usato:

> Questa immagine non si può eliminare. In uso in: categoria «Estate» (campo
> Immagine). Togli prima questi utilizzi e riprova.

Il controllo vale per tutte le strade:

| Strada | Cosa succede |
|---|---|
| Pagina della Resource (`.../{id}/delete/`) | si torna alla scheda con l'avviso |
| API generata (`DELETE`) | risposta 422 con il motivo in `errors.delete` |
| Bottone "Elimina" dell'elenco (`api/backend/delete`) | risposta 422, avviso |
| File tolto dall'elenco (`api/backend/file/delete`) | risposta 422, avviso (`assertFileDeletable()`) |

Se il registro non risponde (tabella non ancora creata), l'eliminazione si
ferma: meglio un media in più che un'immagine sparita da una pagina.

La scheda di un media in uso mostra in alto dove è usato. Caricare un file
nuovo dalla scheda resta possibile: è il modo per sostituirlo ovunque.

## Filtro "usata / non usata"

Gli elenchi di Media hanno il filtro **Utilizzo**, con i testi `full` ed
`empty` della Resource. Legge il registro con una condizione propria
(`filterQuery()`), non una colonna:

```php
MediaUsages::filterCondition(['used']);
// EXISTS (SELECT 1 FROM `media_usages` WHERE `media_usages`.`media_id` = `media`.`id`)
```

## Migrazione delle icone raster

Prima di questa versione `IconResource` accettava png, jpg, jpeg e webp. Con
`forge update` le icone che non sono svg diventano immagini
(`Wonder\App\Media\RasterIconMigration`, dopo l'allineamento delle tabelle):

1. i file dell'icona (originale, webp e misure `nome-<larghezza>`) si
   **copiano** da `assets/upload/icons` a `assets/upload/images`;
2. la riga passa a `type = 'image'`. L'id non cambia, quindi gli utilizzi
   registrati restano validi.

Regole di sicurezza:

- **Niente si cancella e niente si sovrascrive.** Gli originali restano in
  `icons/`: un indirizzo scritto a mano in un template continua a rispondere.
  Si possono togliere a mano quando non servono più.
- **Collisioni.** Se in `images/` c'è già un file diverso con lo stesso nome,
  l'icona prende un nome nuovo (`nome-icona.png`, poi `nome-icona2.png`, ...)
  e la riga punta a quello. Lo slug resta, salvo che un'immagine abbia già lo
  stesso slug: allora cambia anche lui allo stesso modo.
- **Idempotente.** Una riga cambia tipo solo dopo che tutti i suoi file sono
  al loro posto. Un file già copiato e identico non è una collisione, quindi
  un giro interrotto riprende senza doppioni. Il secondo giro non fa niente.
- **Righe lasciate stare.** Un'icona il cui file manca da `icons/` mentre il
  suo nome è occupato in `images/`, o i cui file non si riescono a copiare,
  resta icona ed è elencata nel risultato dell'update
  (`stats.media_raster_icons.skipped`).

La decisione è codice puro (`RasterIconPlan::plan()`), provato senza database
in `tests/App/Media/RasterIconPlanTest.php`.

### Cosa cambia per chi leggeva le icone

- Il pacchetto `wonder-image/app` non ha lettori di `media` per slug o per
  tipo fuori dalle Resource di Media e dall'upload di massa.
- Un sito o un modulo che legge `media` con `type = 'icon'` non trova più le
  icone raster: ora hanno `type = 'image'`. Una lettura solo per slug continua
  a funzionare, perché lo slug resta (tranne nel caso di collisione qui
  sopra).
- Le API generate `app/media/icons` elencano solo gli svg; le icone raster
  compaiono in `app/media/images`, con il loro `alt`.

## Test

```bash
php tests/App/Media/MediaTypesTest.php
php tests/App/Media/MediaUsagesTest.php
php tests/App/Media/MediaDeleteGuardTest.php
php tests/App/Media/RasterIconPlanTest.php
php tests/App/Media/RasterIconMigrationTest.php
php tests/App/Media/MediaStableSlugTest.php
php tests/App/Media/MediaInputsTest.php
php tests/App/Media/MediaFileTest.php
php tests/Themes/MediaPickerTest.php
```

Libreria e registro sono sostituibili insieme:

```php
use Wonder\App\Media\MediaLibrary;
use Wonder\App\Media\MemoryMediaStore;

MediaLibrary::useStore(new MemoryMediaStore([
    ['id' => 10, 'type' => 'image', 'slug' => 'scarpa', 'name' => 'Scarpa', 'file' => 'scarpa.jpg'],
]));
```

`useStore(null)` torna al database (`DatabaseMediaStore`).
