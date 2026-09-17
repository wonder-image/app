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

## Tabelle

| Tabella | Contenuto | Sync |
|---|---|---|
| `society_locations` | sedi | `multiRow()->keepIds()`: gli `id` restano uguali tra locale e produzione |
| `society_location_hours` | orari regolari e secondari | no, dati di produzione |
| `society_location_special_hours` | orari speciali e chiusure | no, dati di produzione |

I loghi restano unici per la società (`logos`).

## Sede predefinita ed eredità

- C'è sempre una sola sede predefinita: impostarne una toglie il flag alle altre e le passa il nome dell'attività, la prima sede creata lo diventa, non si può eliminare.
- **Nome dell'attività** (`name`): si compila solo nella predefinita (il campo compare quando "Predefinita" è "Sì") e vale per tutte le sedi, anche per una sede in franchising con dati legali propri.
- **Slug:** generato dal nome della sede alla creazione, reso unico, mai modificabile; nella scheda è in sola lettura.
- **Contatti** (`email`, `pec`, `tel`, `cel`) e **link** (`site`, `instagram`, `facebook`, `tiktok`, `linkedin`, `whatsapp`, `youtube`): campo per campo.
- **Dati legali**, **indirizzo** (con Place ID) e **sede legale**: per gruppo intero, solo se il gruppo della sede è tutto vuoto (il paese da solo non conta). Così non si mescolano dati di sedi diverse.
- **Orari**: una sede senza orari propri usa orari e chiusure della predefinita.
- Nel form i campi vuoti mostrano come suggerimento il valore ereditato.

## Orari e chiusure

Nel form l'orario di chiusura a mezzanotte si mostra come 00:00 (il campo orario del browser non accetta 24:00) e si salva come `24:00`.

Orari e chiusure si modificano nella scheda della sede, riservata ad `admin`, anche in produzione (la tabella delle sedi non è `localOnly()`). Si salvano insieme alla sede: se una riga non è valida la sede non viene salvata e il messaggio indica riga e problema. Il modello è quello di Google, così un futuro cron potrà confrontarlo con la scheda Google Business.

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

Se `gmaps` è vuoto e c'è un Place ID, `gmaps` diventa un link a Google Maps costruito senza chiave API.

Per orari e aperture:

```php
use Wonder\App\Support\SocietyLocations;

$sede = SocietyLocations::find('negozio-milano') ?? SocietyLocations::default();
SocietyLocations::isOpen($sede);                            // adesso, nel fuso orario del sito
SocietyLocations::hoursFor($sede, new DateTimeImmutable()); // [['open' => '09:00', 'close' => '13:00', 'overnight' => false], ...]
SocietyLocations::all();                                    // sedi visibili
```

## Siti esistenti

Al primo `forge update` dopo l'aggiornamento, se `society_locations` è vuota, la sede predefinita (`id = 1`, "Sede principale") viene creata da `society`, `society_address`, `society_legal_address` e `society_social`, con gli orari di `society_timetable` (o del JSON `society_address.timetable`). Le vecchie tabelle restano per una versione ma non sono più scritte né sincronizzate. Prima della migrazione `infoSociety()` legge ancora le vecchie tabelle.

## Moduli

Gli altri moduli puntano all'`id` delle sedi. Un modulo che vuole le sedi modificabili solo in locale sostituisce `SocietyLocationResource` con una propria Resource di priorità maggiore che sovrascrive `isReadonly()`; in quel caso anche orari e chiusure diventano in sola lettura fuori dal locale.

## In futuro

- Cron che confronta gli orari con la scheda Google tramite il Place ID.
- Place ID dall'autocomplete dell'indirizzo.
- Embed della mappa generato in automatico.
