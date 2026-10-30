---
icon: building
---

# Sedi

## Cos'è

"Sedi" (menu Set Up, ex "Dati aziendali") è l'elenco delle **sedi della società**. Ogni sede ha nome della sede, indirizzo con Google Place ID, contatti, dati legali, sede legale, link, orari e chiusure. Una sede è **predefinita**: contiene il nome dell'attività e le altre sedi prendono da lei ciò che manca.

| Nome | Esempio | Dove si legge |
|---|---|---|
| Nome dell'attività | McDonald's | `$SOCIETY->name`, uguale per tutte le sedi |
| Nome della sede | MC Nembro, MC Drive Dalmine, Orio Center | `$SOCIETY->location->name` |

## Dove si trova nel codice

| Cosa | File |
|---|---|
| Tabelle | `class/App/Models/Config/SocietyLocation.php`, `SocietyLocationHour.php`, `SocietyLocationSpecialHour.php` |
| Eredità | `class/App/Support/SocietyLocationResolver.php` |
| Orari | `class/App/Support/OpeningHours.php` |
| Lettura | `class/App/Support/SocietyLocations.php`, `infoSociety()` in `app/function/info.php` |
| Backend | `class/App/Resources/Config/SocietyLocationResource.php` |
| Validazione degli orari | `class/App/Support/OpeningHoursInput.php` |
| Migrazione | `class/App/Support/SocietyLocationsMigration.php` |
| Allineamento con Google | `class/App/Support/GooglePlaces/*`, task `class/App/Scheduler/Tasks/GooglePlacesTask.php` |
| Recensioni nel sito | `class/Elements/Components/GoogleReviews.php` |

## Tabelle

| Tabella | Contenuto | Sync |
|---|---|---|
| `society_locations` | sedi | `multiRow()->keepIds()`: gli `id` restano uguali tra locale e produzione |
| `society_location_hours` | orari regolari e secondari | no, dati di produzione |
| `society_location_special_hours` | orari speciali e chiusure (`source`: `manual` o `google`) | no, dati di produzione |
| `society_location_google` | ultima lettura di Google per sede: stato, voto medio, numero di recensioni, link Maps, orari, differenze, errore | no, dati di produzione |
| `society_location_reviews` | recensioni di Google per sede (al massimo 5) | no, dati di produzione |

I loghi restano unici per la società (`logos`).

## Sede predefinita ed eredità

- C'è sempre una sola sede predefinita: impostarne una toglie il flag alle altre e le passa il nome dell'attività, la prima sede creata lo diventa, non si può eliminare.
- **Nome dell'attività** (`name`): si compila solo nella predefinita (il campo compare quando "Predefinita" è "Sì") e vale per tutte le sedi, anche per una sede in franchising con dati legali propri.
- **Slug:** generato dal nome della sede alla creazione e mai modificato; si vede nell'elenco delle sedi, non nella scheda. È unico tra le sedi non eliminate (nessun indice `UNIQUE`: una sede eliminata resta nella tabella e il suo nome si può riusare).
- **Contatti** (`email`, `pec`, `tel`, `cel`) e **link** (`site`, `instagram`, `facebook`, `tiktok`, `linkedin`, `whatsapp`, `youtube`): campo per campo.
- **Dati legali**, **indirizzo** (con Place ID) e **sede legale**: per gruppo intero, solo se il gruppo della sede è tutto vuoto (il paese da solo non conta). Così non si mescolano dati di sedi diverse.
- **Orari**: una sede senza orari propri usa orari e chiusure della predefinita.
- Nel form i campi vuoti mostrano come suggerimento il valore ereditato.

## Orari e chiusure

Nel form l'orario di chiusura a mezzanotte si mostra come 00:00 (il campo orario del browser non accetta 24:00) e si salva come `24:00`.

Orari e chiusure si modificano nella scheda della sede, riservata ad `admin`, anche in produzione (la tabella delle sedi non è `localOnly()`). Si salvano insieme alla sede: se una riga non è valida la sede non viene salvata e il messaggio indica riga e problema. Il modello è quello di Google: il task `wonder.google-places` lo confronta con la scheda Google (vedi [Recensioni Google](#recensioni-google)).

**Orari regolari e secondari** (`regularHours`):

- `hours_type`: `regular` o un tipo secondario di Google (`delivery`, `takeout`, `pickup`, `kitchen`, …);
- più fasce nello stesso giorno sono più righe;
- la chiusura può essere il giorno dopo; `24:00` è la mezzanotte a fine giornata;
- una riga senza chiusura indica "sempre aperto".

**Orari speciali e chiusure** (`specialHours`, solo per gli orari regolari):

- chiusura: anche su più giorni (es. ferie dal 10 al 25 agosto);
- apertura straordinaria: un giorno, al massimo fino al giorno dopo se chiude dopo la mezzanotte;
- una chiusura prevale su un'apertura dello stesso giorno.

## Leggere i dati

```php
$SOCIETY;                       // sede predefinita, caricata a ogni richiesta
infoSociety();                  // sede predefinita
infoSociety('negozio-milano');  // per slug o id; se non esiste, la predefinita
infoSocietyLocations();         // tutte le sedi visibili, stesso formato
```

`infoSociety()` restituisce gli stessi campi di sempre (`name`, `email`, `tel`, `prettyAddress`, `prettyLegal`, `social`, `timetable`, `prettyTime`, loghi…) più:

| Campo | Contenuto |
|---|---|
| `location` | `id`, `slug`, `name` (nome della sede), `is_default` |
| `google_place_id` | Place ID della sede |
| `hours` | righe degli orari effettivi |
| `specialHours` | orari speciali da oggi in avanti (`closed` booleano) |
| `businessStatus` | `operational`, `closed_temporarily`, `closed_permanently`, `future_opening` |
| `google` | ultima lettura di Google (`GooglePlaceData::snapshot()`) o `null` |
| `reviews` | recensioni di Google, dalla più recente (`GooglePlaceData::reviews()`) |

Se `gmaps` è vuoto e c'è un Place ID, `gmaps` diventa un link a Google Maps costruito senza chiave API.

Per orari e aperture:

```php
use Wonder\App\Support\SocietyLocations;

$sede = SocietyLocations::find('negozio-milano') ?? SocietyLocations::default();
SocietyLocations::isOpen($sede);                            // adesso, nel fuso orario del sito
SocietyLocations::hoursFor($sede, new DateTimeImmutable()); // [['open' => '09:00', 'close' => '13:00', 'overnight' => false], ...]
SocietyLocations::all();                                    // sedi visibili
```

## Mappa Google

`Iframe::fromLocation($sede, $vista, $zoom, $lingua)` crea l'iframe della Maps Embed API dal Place ID della sede, o dalle coordinate `lat`/`lng` se manca. `$vista` è `roadmap` (default) o `satellite`, le sole viste che la Embed API accetta. Con un Place ID qualsiasi: `Iframe::googlePlace($placeId, 'satellite')`.

```php
use Wonder\App\Support\{GoogleMapsEmbed, SocietyLocations};
use Wonder\Elements\Media\Iframe;

$sede = SocietyLocations::default();

if (GoogleMapsEmbed::forLocation($sede) !== '') {
    echo Iframe::fromLocation($sede, 'satellite', 16)->ratio('16:9')->deferred();
}
```

Usa la chiave browser `GCP_CLIENT_API_KEY` (la stessa di `GoogleMaps`), che deve avere la Maps Embed API abilitata. Senza chiave o senza luogo `GoogleMapsEmbed` restituisce una stringa vuota e `Iframe` lancia `InvalidArgumentException`: controllare prima come nell'esempio. Per una mappa con marker o percorsi usare il componente `GoogleMap`.

## Recensioni Google

Il task `wonder.google-places` (Dev → Scheduler, "Allineamento sedi con Google") legge ogni notte alle 03:30 da Places API (New), Place Details, ogni sede con un Place ID proprio. È **sempre attivo**: dal backend si cambiano orario e parametri, non si sospende. Richiede la chiave server `GCP_API_KEY` nel `.env`, con Places API (New) abilitata; senza chiave l'esecuzione viene registrata come saltata. La chiave va nell'header `X-Goog-Api-Key`, mai nell'URL. Una chiamata per sede, con un field mask limitato ai campi usati.

Gli orari dipendono dal campo **Sincronizzazione orari da Google** della sede (colonna `google_sync_hours`, default `false`):

| Valore | Effetto |
|---|---|
| No (`false`) | gli orari restano quelli inseriti a mano; il task non salva differenze e la scheda non le mostra |
| Sì (`true`) | sostituisce i tipi di orario diversi da Google e gli orari speciali di Google da oggi in avanti; le differenze rimaste (es. lo stato) compaiono nella scheda |

Copia di Google e recensioni si salvano in entrambi i casi.

| Parametro | Default | Effetto |
|---|---|---|
| `language` | `''` | lingua delle recensioni; vuoto = lingua predefinita del sito |

Il vecchio parametro `apply_hours` si ignora: ora decide ogni sede.

Per ogni sede salva:

- in `society_location_google` stato dell'attività, voto medio, numero totale di recensioni, link a Google Maps, orari letti e, con la sincronizzazione attiva, differenze con gli orari locali; se la lettura non riesce salva l'errore e lascia i dati precedenti;
- in `society_location_reviews` le recensioni restituite da Google (al massimo 5, le "più pertinenti" per Google, non l'archivio completo). Quelle che Google non restituisce più vengono eliminate.

Regole:

- Lo **stato dell'attività** non si applica mai: resta una differenza.
- Una riga degli orari speciali scritta a mano (`source = manual`) sulla stessa data ha la precedenza su quella di Google.
- Una sede che eredita gli orari dalla predefinita, con la sincronizzazione attiva, riceve orari propri.
- Una riga di Google modificata a mano nel form resta `source = google` (la colonna è nascosta): il prossimo allineamento con la sincronizzazione attiva la sovrascrive se la data è da oggi in avanti. Per tenerla, eliminala e ricreala.
- Se si toglie o si cambia il Place ID, la copia di Google e le recensioni della sede vengono eliminate al passaggio successivo; nel frattempo `SocietyLocations` non le restituisce.
- Il Place ID si imposta in locale e arriva in produzione con `shared/sync-data.json`. Se nel file è vuoto, il deploy non cancella quello già impostato: per toglierlo, svuotalo in locale e anche nel backend di produzione.
- **Sincronizzazione orari da Google** e stato dell'attività (`google_sync_hours`, `business_status`) non passano dal file: si impostano dal backend di ogni ambiente e il deploy non li tocca.
- Il task fallisce solo se nessuna sede è stata letta; gli errori delle singole sedi stanno nel log dell'esecuzione e nella scheda della sede.

Nella scheda della sede, sopra il form, compaiono le differenze con Google (solo con la sincronizzazione attiva) e l'ultimo errore (`SocietyLocationResource::formNotices()`).

Nel sito:

```php
use Wonder\App\Support\SocietyLocations;
use Wonder\Elements\Components\GoogleReviews;

$sede = SocietyLocations::default();
echo GoogleReviews::fromLocation($sede)->limit(3);
```

`GoogleReviews` stampa le attribuzioni richieste da Google: avatar, nome e profilo dell'autore, link alla recensione su Google Maps, avviso sull'ordine delle recensioni e la scritta «Google Maps» non tradotta. Una recensione tradotta mostra l'originale. Vedi la scheda nel catalogo dei componenti (Dev → Componenti).

**Norme di Google.** Le [norme di Places API](https://developers.google.com/maps/documentation/places/web-service/policies) permettono di conservare solo il Place ID: salvare recensioni, voto e orari è una scelta del sito, non una conformità. Il task limita il rischio aggiornando ogni giorno, eliminando le recensioni non più restituite e tutto quando si toglie il Place ID. Altri limiti noti:

- l'attribuzione è testuale («Google Maps»), non il logo;
- `visitDate`, obbligatoria per le recensioni di luoghi in Francia, non è salvata.

## Siti esistenti

Al primo `forge update` dopo l'aggiornamento, se `society_locations` è vuota, la sede predefinita (`id = 1`, "Sede principale") viene creata da `society`, `society_address`, `society_legal_address` e `society_social`, con gli orari di `society_timetable` (o del JSON `society_address.timetable`). Le vecchie tabelle restano per una versione ma non sono più scritte né sincronizzate. Prima della migrazione `infoSociety()` legge ancora le vecchie tabelle.

## Moduli

Gli altri moduli puntano all'`id` delle sedi. Un modulo che vuole le sedi modificabili solo in locale sostituisce `SocietyLocationResource` con una propria Resource di priorità maggiore che sovrascrive `isReadonly()`; in quel caso anche orari e chiusure diventano in sola lettura fuori dal locale.

## In futuro

- Place ID dall'autocomplete dell'indirizzo.
- Embed della mappa generato in automatico.
