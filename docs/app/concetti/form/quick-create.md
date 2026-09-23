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

// Default: il modal mostra i campi OBBLIGATORI di CategoryResource
FormField::key('category_id')
    ->select($categorie)
    ->quickCreate(CategoryResource::class);
```

`quickCreate(string $resourceClass, ?array $fields = null, ?Closure $layout = null, ?string $label = null, ?string $button = null)`:

- **`$resourceClass`** — la Resource collegata. Deve esporre lo **store API**
  (`apiSchema()` con `store`).
- **`$fields`** — chiavi del sottoinsieme. **`null` (default) = i campi
  obbligatori del target** (rilevati da `formSchema()`). I campi si **riusano**
  dalla risorsa target (`Target::getInput($key)`): stesso tipo, stessa
  validazione.
- **`$layout`** — layout custom del modal: una closure che ritorna un
  `Form`/`Container`/`Card` composto con `Target::getInput(...)`. `null`
  (default) = i campi avvolti in un **`Container`**. Puoi anche riusare il
  pannello della risorsa: `layout: fn() => Target::formLayoutSchema()`.
- **`$label`** — campo etichetta dell'opzione. `null` (default) =
  `name` / `title` / primo campo mostrato.
- **`$button`** — testo del bottone e titolo del modal. `null` (default) =
  «Aggiungi <`label()` della risorsa>». Serve quando la risorsa ha un nome
  tecnico e il campo uno più chiaro: `button: 'Aggiungi opzione'` su un elenco
  di valori d'attributo.

Input supportati (v1): `select` / `selectSearch`, `checkbox` / `checkTree`,
`searchRemote`, `dynamicCheck`. Il metodo `quickCreate()` vive sul concern
`Inputs\Concerns\HasQuickCreate`.

### Esempi per famiglia di input

```php
// Default: i campi OBBLIGATORI del target
FormField::key('category_id')->select($categorie)
    ->quickCreate(CategoryResource::class);

// FK singola (select) con subset esplicito
FormField::key('category_id')->select($categorie)
    ->quickCreate(CategoryResource::class, ['name']);

// FK singola con ricerca
FormField::key('brand_id')->selectSearch($brand)
    ->quickCreate(BrandResource::class, ['name', 'slug'], label: 'name');

// Relazione multipla ad albero (many-to-many)
FormField::key('tags')->checkTree($albero)
    ->quickCreate(TagResource::class, ['name']);

// Select remoto (opzioni via AJAX)
FormField::key('supplier_id')->searchRadio(__r('backend.resource.suppliers.index'))
    ->quickCreate(SupplierResource::class, ['company_name', 'vat'], label: 'company_name');

// Check caricati via AJAX
FormField::key('roles')->dynamicCheck(__r('backend.resource.roles.index'))
    ->quickCreate(RoleResource::class, ['name']);

// Layout custom del modal: pannello arrangiato da te, input da getInput()
FormField::key('category_id')->select($categorie)
    ->quickCreate(CategoryResource::class, layout: fn() => (new Form)->components([
        (new Card)->components([
            CategoryResource::getInput('name'),
            CategoryResource::getInput('slug'),
        ]),
    ]));
```

### Precondizione: lo store API della risorsa target

La creazione passa dallo **store API** della risorsa collegata: il target deve
esporlo. Basta che l'`apiSchema()` includa `store` con i campi del sottoinsieme:

```php
// In CategoryResource
public static function apiSchema(): ApiSchema
{
    return ApiSchema::for(static::class)
        ->only(['store'])
        ->fields('store', ['name']);   // deve includere le chiavi del subset
}
```

Non serve permettere lo store a ruoli backend: la chiamata viene fatta come
utente `@system` (`api_internal_user`). Il controllo dei permessi è sul
**bottone** (visibile solo a chi può creare quella risorsa) e ripetuto nel
proxy server-side.

## Come funziona (flusso)

1. Il renderer Bootstrap emette il "+" e un modal **solo** se l'utente backend
   corrente è autorizzato a creare la risorsa target
   (`permissionSchema()->get('backend')['create']`, ripiego su `store`).
2. All'invio, un piccolo JS fa POST a `backend.resource.quick-create`
   (`app/http/backend/resource/quick-create.php`), gato dalla sessione backend.
3. Il `QuickCreateController` ri-verifica il permesso, rimuove i campi di
   controllo del modal e passa il resto allo **store API** della risorsa target
   (che filtra coi propri `apiSchema('store')`), **lato server come `@system`**
   (`api_internal_user`): il token non tocca mai il browser.
4. Dalla risposta dello store estrae `{id, label, item}` e il JS
   inserisce+seleziona la nuova opzione. `item` è la riga salvata, completata
   dai valori semplici scritti nel modal (vince la riga salvata): lo store
   spesso restituisce poco più dell'id, e chi riceve la riga può aver bisogno
   del resto — per esempio del genitore sotto cui mettere una categoria.
5. Il modal torna com'era all'apertura della pagina, non vuoto: un campo con
   un valore proposto lo ripropone alla creazione dopo. Gli alberi dentro il
   modal restano come sono, così si creano di fila più figli dello stesso
   genitore.

## Vincoli

- I campi mostrati **devono bastare a creare una riga valida**. Il default (i
  campi obbligatori del target) di norma basta; se mostri un subset o un layout
  parziale, gli altri campi obbligatori del target devono avere un default,
  altrimenti lo store rifiuta.
- **Campi obbligatori vuoti**: il modal non è un `<form>`, quindi la validazione
  HTML5 non parte da sola. Doppio argine, così non si crea mai una riga vuota:
  il **client** valida i campi con `checkValidity()` prima dell'invio, e il
  **proxy backend** (`QuickCreateController::missingRequired`) rifiuta con `422`
  i campi obbligatori mostrati ma vuoti — prima ancora di chiamare lo store.
- Gli **errori** (validazione e store) si mostrano nel modal come **alert**
  (coerente con [Notifiche → Errori dei form](../notifiche.md#errori-dei-form)),
  mai come testo inline.
- **v1 single-level**: il form del modal non ha a sua volta un quick-create.
- **Posizione del "+"**: per i controlli singoli (`select`/`selectSearch`/
  `searchRemote`) è **attaccato all'input a destra** in un `input-group`
  (versione floating); per i **gruppi** (`checkbox`/`checkTree`/`dynamicCheck`)
  è una **testata in alto a destra** — `+ Aggiungi <Nome risorsa>`, sulla riga
  del titolo del gruppo. Il nome viene da `button:` se dichiarato, altrimenti
  da `Resource::label()` (la stessa fonte di `defaultPageTitles()['create']`),
  con ripiego sullo slug. Per i gruppi a pillole (`checkbox()->pills()`) il
  "+" è l'**ultima pillola della riga**, tratteggiata: la voce nuova nasce
  come pillola spuntata accanto alle altre, prima del "+", e lancia `change`
  così chi ascolta il gruppo (un repeater che genera righe, per esempio) la
  vede.
- **Adapter JS**: il framework emette sempre l'evento `wi:quick-create:created`
  e `wonder-image/lib` (`src/build/backend/js/form/quickCreate.js`) inserisce e
  seleziona l'opzione nel widget. Coperti: `select` (baseline nel framework),
  `selectSearch` (select2, con re-render), `dynamicCheck` (card AJAX) e
  `checkTree` (jstree). `searchRemote` sul backend è un input inerte (il
  comportamento remoto vive solo sul tema Wonder). Nel `checkTree` il nodo
  nasce **sotto il genitore** (`item.parent_id`) quando l'albero lo contiene,
  altrimenti in cima; solo un albero a spunte lo spunta, uno a scelta singola
  (il «padre» dentro un modal) lo aggiunge e basta.
- **La riga appartiene alla risorsa, non al campo**: il detail dell'evento
  porta anche `resource` (lo slug), e l'adapter aggiorna **ogni** campo della
  pagina marcato `data-wi-qc-resource` con quello slug — non solo quello che
  ha aperto il modale. Un campo che la risorsa la elenca soltanto lo dichiara
  con `->listsResource(XResource::class)`. È così che una categoria creata dal
  select «Categoria principale» compare anche nell'albero «Categorie» della
  stessa scheda.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| Dichiarazione (concern) | `class/App/ResourceSchema/Inputs/Concerns/HasQuickCreate.php` |
| Campi/label/corpo del modal | `class/Backend/Support/QuickCreatePanel.php` |
| Permesso (fonte unica) | `class/Backend/Support/QuickCreateAuthorizer.php` |
| Proxy backend | `class/Backend/Support/QuickCreateController.php` + `app/http/backend/resource/quick-create.php` |
| Rotta | `app/config/routes/route.backend.php` (`backend.resource.quick-create`) |
| Render "+" + modal + JS (emette `wi:quick-create:created`) | `class/Themes/Bootstrap/Form/Field.php` |
| Adapter widget (select2 / card AJAX) | `wonder-image/lib` → `src/build/backend/js/form/quickCreate.js` |

Spec e piano: `docs/superpowers/specs/2026-09-21-backend-fk-quick-create-design.md`,
`docs/superpowers/plans/2026-09-21-backend-fk-quick-create.md`.
