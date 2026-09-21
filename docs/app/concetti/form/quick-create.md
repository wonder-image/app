---
icon: square-plus
---

# Creazione rapida da campo FK

## Cos'è

Su un campo backend che rappresenta una **foreign key** puoi abilitare un
pulsante **"+ Aggiungi"** che apre un **modal nella stessa pagina** per creare
al volo una riga della **risorsa collegata** (un sottoinsieme di campi che
dichiari tu). Al salvataggio, la riga viene creata subito e l'opzione viene
aggiunta e selezionata nell'input.

Esempio: sul form Prodotto, accanto al select Categoria, un "+ Aggiungi
categoria" crea la categoria senza lasciare la pagina.

## Come si dichiara

```php
use Wonder\App\Resources\CategoryResource;

FormField::key('category_id')
    ->select($categorie)
    ->quickCreate(CategoryResource::class, ['name'], label: 'name');
```

`quickCreate(string $resourceClass, array $fields, ?string $label = null)`:

- **`$resourceClass`** — la Resource collegata. Deve esporre lo **store API**
  (`apiSchema()` con `store`).
- **`$fields`** — le chiavi del **sottoinsieme** mostrate nel modal. I campi si
  **riusano** dalla risorsa target (`Target::getInput($key)`): stesso tipo,
  stessa validazione.
- **`$label`** — il campo che fa da etichetta dell'opzione (default: il campo
  label della risorsa target).

Input supportati (v1): `select` / `selectSearch`, `checkbox` / `checkTree`,
`searchRemote`, `dynamicCheck`. Il metodo `quickCreate()` vive sul concern
`Inputs\Concerns\HasQuickCreate`.

## Come funziona (flusso)

1. Il renderer Bootstrap emette il "+" e un modal **solo** se l'utente backend
   corrente è autorizzato a creare la risorsa target
   (`permissionSchema()->get('backend')['create']`, ripiego su `store`).
2. All'invio, un piccolo JS fa POST a `backend.resource.quick-create`
   (`app/http/backend/resource/quick-create.php`), gato dalla sessione backend.
3. Il `QuickCreateController` ri-verifica il permesso, whitelista il
   sottoinsieme, e chiama lo **store API** della risorsa target **lato server
   come `@system`** (`api_internal_user`): il token non tocca mai il browser.
4. Dalla risposta dello store estrae `{id, label}` (dall'item creato) e il JS
   inserisce+seleziona la nuova opzione.

## Vincoli

- Il **sottoinsieme deve bastare a creare una riga valida**: i campi
  obbligatori del target non mostrati nel modal devono avere un default,
  altrimenti lo store rifiuta.
- Gli **errori dello store** si mostrano nel modal come **alert** (coerente con
  [Notifiche → Errori dei form](../notifiche.md#errori-dei-form)), mai come
  testo inline.
- **v1 single-level**: il form del modal non ha a sua volta un quick-create.
- Gli adapter JS per `select` sono completi; `checkTree` / `searchRemote` /
  `dynamicCheck` dipendono dai widget di `wonder-image/lib` e vanno verificati
  in un sito.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Dichiarazione (concern) | `class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php` |
| Permesso (fonte unica) | `class/Backend/Support/QuickCreateAuthorizer.php` |
| Proxy backend | `class/Backend/Support/QuickCreateController.php` + `app/http/backend/resource/quick-create.php` |
| Rotta | `app/config/routes/route.backend.php` (`backend.resource.quick-create`) |
| Render "+" + modal + JS | `class/Themes/Bootstrap/Form/Field.php` |

Spec e piano: `docs/superpowers/specs/2026-09-21-backend-fk-quick-create-design.md`,
`docs/superpowers/plans/2026-09-21-backend-fk-quick-create.md`.
