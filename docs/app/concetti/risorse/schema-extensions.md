---
icon: puzzle-piece
---

# Schema extension

## Cos'è

Una **schema extension** è una classe riusabile che genera **frammenti di
schema** per `Model`, `Resource` e `CustomPageSchema` senza introdurre hook
automatici nel core.

Serve quando un blocco di campi ricorre in più punti e deve restare coerente
tra:

- `Model::dataSchema()`
- `Model::tableSchema()`
- `Resource::labelSchema()`
- `Resource::formSchema()` o `CustomPageSchema`

## A cosa serve

Elimina duplicazione quando hai bundle di campi ripetuti, ad esempio:

- indirizzi
- contatti
- blocchi SEO
- coordinate / geolocalizzazione
- payload fiscali o anagrafici riusabili

Il pattern corretto è comporre array di schema, non registrare automaticamente
comportamenti sul base `Model` o `Resource`.

## Dove si trova nel codice

- Namespace consigliato: `Wonder\App\Schema\Extensions`
- Prima extension core: `class/App/Schema/Extensions/AddressExtension.php`

## Regola d'uso

Una schema extension deve restare:

- **HTML-agnostic**
- **riusabile**
- **composta da frammenti**
- **override-friendly** per siti e moduli

Non deve fare:

- rendering HTML
- query SQL
- persistenza diretta
- side effect runtime

## Esempio: `AddressExtension`

```php
use Wonder\App\Schema\Extensions\AddressExtension;

$address = AddressExtension::simple(
    prefix: 'legal',
    linkKey: 'gmaps',
    countryDefault: 'IT',
)
    ->allowedCountries(['IT', 'DE'])
    ->requiredFields(['country', 'province', 'city', 'street', 'number']);
```

### In un Model

```php
public static function dataSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->dataSchema(),
    ];
}

public static function tableSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->tableSchema(),
    ];
}
```

### In una Resource

```php
public static function labelSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->labels(),
    ];
}

public static function formSchema(): array
{
    return [
        ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->formSchema(),
    ];
}
```

### In una `CustomPageSchema`

```php
public static function legalAddressFormSchema(string $country = 'IT'): array
{
    return static::applyLabelSchema(
        AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->formSchema($country)
    );
}
```

## `AddressExtension` in breve

Supporta:

- `prefix` opzionale (`country` vs `legal_country`)
- profilo `simple`
- profilo `billing`
- label via `__t()`
- campi dati (`dataSchema()`)
- DDL (`tableSchema()`)
- form backend (`formSchema()`)
- decorazione righe (`decorate()`)
- default paese coerente anche sul select `country`
- filtro paesi ammessi con `allowedCountries([...])`
- campi obbligatori con `requiredFields([...])`
- autocompletamento Google sulla via, place ID e coordinate con `withGeo()`

I profili disponibili sono:

- `AddressExtension::simple(...)`
- `AddressExtension::billing(...)`

## Paese di default e paesi ammessi

Se imposti `countryDefault: 'IT'`, la extension ora:

- precarica le province corrette con `states('IT')`
- imposta anche il valore del select `country` su `IT`

Se vuoi limitare i paesi selezionabili:

```php
AddressExtension::simple(countryDefault: 'IT')
    ->allowedCountries(['IT', 'DE']);
```

Il campo `country` mostrerà solo Italia e Germania.

## Autocompletamento Google e geo

Il campo della via ha sempre `data-wi-place="<prefisso>"`: se il sito ha la chiave
Google, `places.js` della lib propone gli indirizzi e scrive via, civico, CAP, città,
paese e provincia nei campi con quel prefisso. Senza chiave il form resta uguale.

Per salvare anche il place ID di Google e le coordinate:

```php
AddressExtension::simple()->withGeo();                    // place_id, lat, lng
AddressExtension::simple(prefix: 'legal')->withGeo();     // legal_place_id, legal_lat, legal_lng
AddressExtension::simple(linkKey: 'gmaps')->withGeo('google_place_id');
```

- `withGeo()` aggiunge le colonne (`lat` e `lng` sono `DECIMAL(10,7)`), i campi con
  i formatter che puliscono i valori e i campi nascosti nel form.
- Con un nome (`'google_place_id'`) il place ID va in una colonna che il modello
  dichiara già da sé: la extension non la crea e la via porta `data-wi-place-id`.
- Con `linkKey` il link a Google Maps si costruisce dalle coordinate e dal place ID.
- `labels()` e `keys()` non contengono i campi geo: per i valori inviati da un form
  usa `GoogleMaps::cleanGeo($input, 'shipping_')`, che rende null i valori non
  validi e tiene le coordinate solo in coppia.
- Se il form ha un layout scritto a mano, i campi nascosti vanno aggiunti al layout
  (`getInput('lat')`, `getInput('lng')`, …).

## Campi obbligatori

Per marcare campi obbligatori usa:

```php
AddressExtension::simple()
    ->requiredFields(['country', 'province', 'city', 'street', 'number']);
```

La lista usa i nomi logici del bundle, senza prefisso:

- `country`
- `province`
- `city`
- `cap`
- `street`
- `number`
- `more`
- `link` oppure il tuo `linkKey` (es. `gmaps`)
- `phone_prefix`
- `phone`
- `name`
- `surname`
- `type`
- `business_name`
- `cf`
- `pi`
- `sdi`
- `pec`

L'effetto vale sia sul form backend (`->required()`) sia sul `dataSchema()`
server-side (`Field::required()`).

## Convenzioni consigliate

- Usa una schema extension quando il bundle ha **almeno due superfici** da
  tenere coerenti (es. dati + form, oppure DDL + label + form).
- Se il blocco è solo visuale, non usare una schema extension: usa un Component.
- Se il blocco è solo persistenza/validazione, valuta prima un `Field`,
  `Concern` o un validator dedicato.
- Se la label deve essere traducibile, usa `__t()` dentro `labels()`.
- Se un consumer vuole cambiare una label, può sempre fare merge nel proprio
  `labelSchema()`.

## Cosa non fa

- Nessuna registrazione automatica sul core
- Nessun fallback cross-theme
- Nessun rendering frontend/backend
- Nessuna logica condizionale UI implicita

Per esempio, `AddressExtension::billing()` definisce il campo `type`, ma non
introduce da sola lo show/hide first-class dei campi `private`/`business`.

## Decorare una riga del Model

Se l'extension conosce abbastanza bene il bundle dati, puo anche esporre un
helper puro per arricchire una riga letta dal Model.

`AddressExtension::decorate()` evita due duplicazioni tipiche:

- passare a mano ogni colonna prefissata al formatter
- rimappare a mano il risultato su chiavi nuovamente prefissate

Esempio:

```php
use Wonder\App\Schema\Extensions\AddressExtension;

public static function decorate(array $row): array
{
    return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->decorate($row);
}
```

Per l'indirizzo, la extension aggiunge automaticamente chiavi derivate come:

- `address`, `prettyAddress`, `prettyPDF`
- con prefisso: `legal_address`, `legal_prettyAddress`, `legal_prettyPDF`
- se presenti campi telefono: anche `prettyPhone` / `legal_prettyPhone`

Internamente usa `Wonder\Support\Prettify\Address::prettifyRow(...)`, che legge
la riga gia prefissata senza costringerti a fare mapping manuale.

## Checklist

- [ ] la classe vive sotto `class/App/Schema/Extensions`
- [ ] espone frammenti riusabili, non side effect
- [ ] copre almeno `labels()`, `dataSchema()`, `tableSchema()`, `formSchema()`
- [ ] eventuali helper come `decorate()` restano puri e senza query/side effect
- [ ] usa `__t()` per le label shipped dal framework
- [ ] la documentazione `docs/app/*`, `AGENTS.md` e la skill Wonder sono
      allineate se il pattern diventa convenzione architetturale
