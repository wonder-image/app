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
);
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

I profili disponibili sono:

- `AddressExtension::simple(...)`
- `AddressExtension::billing(...)`

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

## Checklist

- [ ] la classe vive sotto `class/App/Schema/Extensions`
- [ ] espone frammenti riusabili, non side effect
- [ ] copre almeno `labels()`, `dataSchema()`, `tableSchema()`, `formSchema()`
- [ ] usa `__t()` per le label shipped dal framework
- [ ] la documentazione `docs/app/*`, `AGENTS.md` e la skill Wonder sono
      allineate se il pattern diventa convenzione architetturale
