# Backend FK quick-create — design

**Data:** 2026-09-21
**Repo:** `wonder-image/app` (framework)
**Tipo:** architetturale (capacità trasversale a `FormField` + endpoint backend + sottosistema modal + JS)

## Obiettivo

Nel backend, sulla pagina **add/edit di una Resource**, per un campo che rappresenta una **foreign key** (select/selectSearch, checkbox/checkTree, searchRemote, dynamicCheck), offrire un pulsante **"+ Aggiungi"** che apre un **modal nella stessa pagina** con un **sottoinsieme dichiarato** di campi della risorsa collegata. Al salvataggio, la riga collegata viene creata **subito** e l'input si **aggiorna** con la nuova opzione, **selezionandola**.

Esempio: Prodotto → Categoria. Sul form Prodotto, accanto al select Categoria, "+ Aggiungi categoria" apre un modal che crea la categoria e la aggiunge/seleziona nel select.

## Decisioni prese (in brainstorming)

- **Contenuto del modal:** un **sottoinsieme di campi dichiarato dallo sviluppatore** (non il form completo del target). Le definizioni dei campi si **riusano** dalla risorsa target per chiave (stesso tipo/validazione).
- **Tipi di input v1:** tutti e quattro — select/selectSearch, checkbox/checkTree, searchRemote, dynamicCheck. Architettura estendibile ad altri.
- **Creazione:** via lo **store API** della risorsa target, chiamato **lato server come utente `@system`** (`api_internal_user`), attraverso un **proxy backend** gato dalla sessione. Il token `@system` non tocca mai il browser.
- **Permessi:** il "+" compare solo se l'utente backend corrente è autorizzato a creare la risorsa target; il proxy ripete il check server-side.
- **lib:** non necessaria (feature solo backend, tema Bootstrap, JS inline nel componente — come il Repeater).

## Architettura

```
FormField::key('category_id')->select(...)->quickCreate(CategoryResource::class, ['name'], label: 'name')
        │ (concern HasQuickCreate: memorizza target-slug, subset di chiavi, campo label)
        ▼
Bootstrap renderer dell'input
        │  - "+" reso solo se l'utente può creare il target
        │  - modal Bootstrap (uno per campo) con i campi del subset resi da CategoryResource::getInput($key)
        │  - <script> inline: apertura modal, submit, adapter per tipo
        ▼
POST /backend/resource/quick-create/  (sessione backend, guarded)
        │  1. valida che l'utente possa creare il target
        │  2. curlJson(__r('api.resource.category.store'), 'POST', subset, $systemToken)   ← @system, lato server
        │  3. risponde JSON { success, id, label } | { success:false, error }
        ▼
Client adapter (per famiglia di input): inserisce+seleziona l'opzione {id,label}; chiude il modal
```

## Componenti

### 1. Dichiarazione — `HasQuickCreate` (concern)

- Nuovo concern `Wonder\App\ResourceSchema\Inputs\Concerns\HasQuickCreate` con:
  - `quickCreate(string $resourceClass, array $fields, ?string $label = null): static`
  - memorizza: `target` = `$resourceClass::slug()`, `fields` (chiavi del subset), `label` (campo etichetta; default = campo label della risorsa target da `labelSchema()`/primo campo testuale), e il FQCN target per il render.
  - getter usati dal renderer: `quickCreateConfig(): ?array`.
- Mixato in: `InputSelect`, `InputSelectSearch`, `InputCheckbox`, `InputCheckTree`, `InputSearchRemote`, `InputDynamicCheck`.
- Facade opzionale su `FormField` (delega all'input concreto) per la scoperta.
- **Precondizione validata:** il target deve esporre lo store API (`apiSchema()` con `store`). Se assente, il "+" non viene reso e in dev si logga un avviso (misconfigurazione).

### 2. Server — proxy quick-create

- Rotta: `POST /backend/resource/quick-create/` in `route.backend.php`, `->name('resource.quick-create')->backend()->guarded()->permit([])` (l'autorizzazione fine è per-target, fatta nel controller).
- Handler: `app/http/backend/resource/quick-create.php` → `QuickCreateController` (`class/Backend/Support/`), che:
  1. Risolve il target da `ResourceRegistry::resolve($_POST['resource'])`.
  2. **Verifica permesso:** l'authority dell'utente backend interseca l'**authority di creazione backend** del target — `permissionSchema()->get('backend')['create']` (ripiego su `['store']` se `create` non è dichiarata). Altrimenti 403.
  3. Estrae i soli campi del subset dichiarati (whitelist per-target, per non accettare campi arbitrari).
  4. `curlJson(__r('api.resource.'.$slug.'.store'), 'POST', $subsetValues, $systemToken)` con `$systemToken` da `infoUser('@system','username')->api_internal_user->token` (pattern `home.php`/Scheduler).
  5. **Label:** se il campo label è nel subset usa il valore inviato; altrimenti legge la riga creata per id (label field). Risponde `{ success:true, id, label }`.
  6. **Errore:** su fallimento dello store risponde `{ success:false, error }` (messaggio già pronto per l'alert).
- CSRF: token di sessione incluso nel modal e verificato dal controller.

### 3. Modal — rendering (tema Bootstrap)

- Un modal Bootstrap per campo quick-create (id univoco basato sul nome campo), pattern di `Themes/Bootstrap/Form/Components/Repeater.php`.
- Corpo: i campi del subset resi via `TargetResource::getInput($key)` attraverso la pipeline `FormField` del tema Bootstrap (stessa resa/validazione degli altri input).
- Footer: bottone "Salva". Header: titolo (es. "Aggiungi categoria").
- Il "+" è un `Button` accanto all'input.

### 4. Client — adapter per tipo di input (JS inline)

- Comune: apertura modal, raccolta valori subset, `fetch` POST al proxy (CSRF), gestione `{success,id,label}` / `{success:false,error}`.
- Su successo → adapter per famiglia:
  - **select / selectSearch:** append `<option value=id>label</option>` selezionata; per selectSearch, refresh del widget.
  - **checkbox / checkTree:** nuova voce spuntata; per checkTree (jsTree) `create_node` + `check_node`.
  - **searchRemote:** inietta l'opzione nel set corrente del widget remoto e la seleziona.
  - **dynamicCheck:** ricarica l'endpoint `url` (o inietta la voce) e spunta la nuova.
- Su errore → mostra il messaggio **come alert** nel modal (coerente con la regola "errori dei form via alert").

### 5. Permessi

- **UI:** il renderer Bootstrap rende il "+" solo se `array_intersect(userAuthority, TargetResource::permissionSchema()->get('backend')['create'] ?? ['store'])` non è vuoto. Stessa regola del proxy (fonte unica di verità in un helper condiviso).
- **Server:** il proxy ripete lo stesso check (difesa in profondità) prima di chiamare lo store.

## Vincoli & casi limite

- Il **subset deve bastare** a creare una riga valida: i campi obbligatori del target non mostrati devono avere default, altrimenti lo store rifiuta (errore mostrato nel modal).
- **Single-level:** il form del modal non ha a sua volta un quick-create (niente ricorsione in v1).
- Il target deve avere lo **store API abilitato**; altrimenti "+" non reso.
- La **label** dell'opzione riflette il campo label dichiarato (o quello di default del target).

## Testing

- **PHP lint** su ogni file toccato.
- **Unit-ish:** il concern memorizza la config; il controller: (a) nega senza permesso, (b) whitelista i campi, (c) chiama lo store come `@system`, (d) risponde `{id,label}`, (e) propaga l'errore dello store. (Nuovi test in `tests/`, con `git add -f` — i test veri sono tracciati nonostante `.gitignore`.)
- **Integrazione (da un sito):** `php forge start`; sul form Prodotto, "+ Aggiungi categoria" crea la categoria e il select si aggiorna/seleziona. Verifica anche checkTree/searchRemote/dynamicCheck. (La resa reale del DataTable/AJAX richiede il runtime di un sito.)

## lib (`wonder-image/lib`)

**Non richiesta per la v1.** Feature solo backend (tema Bootstrap, JS inline nel componente). Se in implementazione emergesse un asset genuinamente condiviso del design system, sarà un intervento separato sul repo `wonder-image/lib` (non presente qui), da coordinare a parte.

## Non-obiettivi (YAGNI)

- Quick-create nel tema **frontend** Wonder.
- Quick-create **annidato** (modal dentro modal).
- **Edit** inline della riga collegata (solo create).
- Wiring automatico "FK → opzioni dalla risorsa collegata" (le opzioni restano costruite come oggi; il quick-create aggiunge solo la nuova opzione).
