# Libreria Media

La libreria **Media** è il posto unico dei file pubblici caricati dal backend:
immagini, icone, documenti e video. Un record che usa uno di questi file ne
salva l'**id**, non il percorso: se il file cambia in libreria, cambia ovunque
è usato.

Restano fuori i file privati (fatture, allegati degli ordini, documenti dei
clienti, foto profilo): la libreria è condivisa e pubblica, quelli sono campi
file normali.

Questa pagina descrive i tipi, il registro degli utilizzi e la migrazione
delle icone. Il campo del form che sceglie o carica un media arriva in un
passo successivo e userà quello che è descritto qui.

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
un media senza `sync()` o `forget()` lo lascia bloccato. Chi elimina un record
dall'elenco (cancellazione logica) deve chiamare `forget()` nel suo
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
  compaiono in `app/media/images`, che non espone `alt`. Il valore di `alt`
  resta nella riga.

## Test

```bash
php tests/App/Media/MediaTypesTest.php
php tests/App/Media/MediaUsagesTest.php
php tests/App/Media/MediaDeleteGuardTest.php
php tests/App/Media/RasterIconPlanTest.php
php tests/App/Media/RasterIconMigrationTest.php
```
