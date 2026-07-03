# API badge booleani per le tabelle backend — Design

Data: 2026-07-02
Stato: approvato dall'utente (design), in attesa di piano di implementazione

## Problema

Le colonne `active`, `visible`, `evidence` delle tabelle backend vengono renderizzate
chiamando le funzioni legacy `active()`, `visible()`, `evidence()` di
`app/function/backend/plugin.php`, che leggono `global $NAME` e `global $PATH`.

Nel flusso Resource (`ResourcePageController::renderListTable()` →
`ResourceTableRenderer` → `Table::generate()` → `Table::prerender()` →
`ListProvider::fetch()` → `Field::setValue()`), e nell'endpoint AJAX
`app/http/api/backend/list-table.php`, il global `$NAME` non viene mai popolato:
`LegacyGlobals::set('NAME', ...)` avviene solo nel flusso store/update. Risultato:

- Warning PHP: `Attempt to read property "table" on null` in `plugin.php:84`.
- URL `ajaxRequest` generato rotto (`?table=&id=...`).

Il paradosso: `Field` ha già tutto il contesto iniettato nel costruttore
(`$this->table->name`, `$this->table->database`, `$this->link->api`), ma il
percorso badge lo perde attraversando le funzioni globali. Inoltre `Field`
duplica già testi/icone/colori dei tre stati nei metodi `changeActive()`,
`changeVisible()`, `changeEvidence()` (menu azioni riga): la definizione vive
in due posti e solo uno funziona senza global.

### Problema di sicurezza collegato

`Field::setValue()` esegue `call_user_func_array($functionName, $args)` dove
`$functionName` arriva da `$_POST['fields']` dell'endpoint `list-table`
(`Field.php:603`). Un client autenticato può invocare funzioni PHP arbitrarie
con argomenti parzialmente controllati. Il design include l'hardening.

## Decisioni prese con l'utente

1. **API**: helper semantici + base generica su `TableColumn`.
2. **Nomi**: `activeBadge()` / `visibleBadge()` / `evidenceBadge()` /
   `booleanBadge()` — NON "Toggle": il badge NON è cliccabile di default;
   il toggle sul badge è opt-in esplicito.
3. **Retrocompat**: totale. `->function('active'|'visible'|'evidence', ...)`
   continua a funzionare (rimappato internamente); le Resource del framework
   vengono migrate ai nuovi helper; le funzioni di plugin.php restano come
   wrapper deprecati.
4. **Collocazione**: `class/Backend/Table/Badge/` con API fluente.
5. **Documentazione**: aggiornare `docs/app/*` nello stesso lavoro.

## Design

### 1. `Wonder\Backend\Table\Badge\BooleanBadge` — unica fonte di verità

File: `class/Backend/Table/Badge/BooleanBadge.php`. Puro value object +
renderer, nessun global, nessuna query.

```php
BooleanBadge::active($value)   // preset: Abilitato/check-circle/success — Disabilitato/x-circle/danger; button Disabilita/Abilita
BooleanBadge::visible($value)  // preset: Visibile/eye/success — Nascosto/eye-slash/danger; button Nascondi/Mostra
BooleanBadge::evidence($value) // preset: In evidenza/star-fill/warning — off vuoto; button Rimuovi evidenza/In evidenza
BooleanBadge::make($value)
    ->on(string $text, string $icon = '', string $color = '', string $buttonText = '')
    ->off(string $text, string $icon = '', string $color = '', string $buttonText = '')
    ->action(string $onclick)   // opzionale
    ->clickable(bool $clickable = true)  // opt-in esplicito
```

Metodi di render (HTML identico all'attuale `returnBadge()`, incluse le classi
`pc-none`/`phone-none` e i badge Bootstrap, per non toccare il CSS):
`badge()`, `icon()`, `tooltip()`, `badgeTooltip()`, `automaticResize()`,
`button()` (dropdown-item per il menu azioni, equivalente di `returnButton()`).

Il valore è considerato "on" solo se `=== 'true'` (stringa) o `=== true`;
ogni altro valore è off (comportamento attuale).

### 2. API su `TableColumn` (`class/App/ResourceSchema/TableColumn.php`)

```php
TableColumn::key('active')->activeBadge()->size('little')
TableColumn::key('visible')->visibleBadge()
TableColumn::key('evidence')->evidenceBadge()
TableColumn::key('stato')->booleanBadge()
    ->badgeOn('Aperto', 'bi bi-unlock', 'success')
    ->badgeOff('Chiuso', 'bi bi-lock', 'danger')
    ->badgeVariant('automaticResize')   // default
    ->badgeClickable()                  // forza il toggle sul badge
```

Questi metodi impostano `setType('badge')` e scrivono nello schema un
**descrittore dichiarativo**, senza nomi di funzioni PHP:

```php
['badge' => [
    'preset'    => 'active' | 'visible' | 'evidence' | null,
    'column'    => '<nome colonna>',
    'variant'   => 'automaticResize',
    'clickable' => false,
    'on'  => ['text' => ..., 'icon' => ..., 'color' => ..., 'button' => ...], // solo generico
    'off' => [...],
]]
```

`ResourceTableRenderer::legacyColumnFormat()` propaga la chiave `badge` nel
format come oggi fa con `function`, quindi il descrittore viaggia sia nel
pre-render server-side sia nel config JSON dell'endpoint AJAX.

### 3. Risoluzione in `Field` (il fix del warning)

In `Field::setValue()`: se il format contiene `badge`, la cella è risolta via
`BooleanBadge` usando il contesto iniettato (`$this->table->name`,
`$this->table->database`, `$this->link->api`, `$this->rowId`). Endpoint toggle:
`{api}/backend/change/boolean/?database=...&table=...&column=...&id=...`
(route esistenti; per `active`/`visible` restano valide anche le route alias
`/active/` e `/visible/`). Con `clickable: true` il badge viene avvolto con
l'onclick `ajaxRequest` + reload della DataTable.

**Rimappatura legacy**: se `$format['function']['name']` è
`active`/`visible`/`evidence`, `Field` converte il format nel descrittore
badge equivalente (usando `function.return` come variant) e usa il nuovo
percorso. Le funzioni globali non vengono più chiamate dal renderer: il
warning sparisce anche per i siti non migrati (es. `EventResource` del modulo
rsvp) senza toccarli.

La special-case esistente per la tabella `user` (badge/azione active mostrati
solo se l'utente ha al massimo una area e una authority) viene preservata sia
nel percorso badge sia nel menu azioni.

`changeActive()`, `changeVisible()`, `changeEvidence()` delegano ai preset di
`BooleanBadge` per testi/icone/colori (unica fonte di verità), mantenendo
`$this->ajaxRequest(...)` per l'azione.

### 4. Hardening di `call_user_func`

Per i nomi residui passati a `->function()` (es. `mailService`), `Field`
esegue solo funzioni presenti in una whitelist derivata **server-side**:

- le funzioni dichiarate negli schema registrati (`Wonder\App\Table::$list`
  e `$TABLE` legacy via `LegacyGlobals`), più
- i built-in del framework gestiti come special-case (`empty`,
  `permissions*`).

Un nome arrivato dal POST che non compare in nessuno schema server-side
produce cella vuota (nessuna chiamata, nessun warning). Nessun uso legittimo
si rompe: ogni uso legittimo è per definizione dichiarato in uno schema lato
server.

**Aggiornamento post-implementazione (2026-07-02):** la derivazione della
whitelist da `Wonder\App\Table::$list`/`$TABLE` ipotizzata sopra si è
rivelata impraticabile: questi oggetti contengono il `dataSchema` di input
(i campi accettati in scrittura), non le funzioni colonna usate in lettura
per il rendering; inoltre le pagine legacy dichiarano le proprie funzioni
custom nel codice della pagina stessa, che non viene eseguito durante la
richiesta AJAX di `list-table` (solo lo schema/config viaggia nel POST). La
whitelist effettiva implementata in `ColumnFunctionRegistry` è quindi
composta da: i default del framework (funzioni built-in note, es.
`mailService`, `mailLogStatus`), le funzioni esposte via `ResourceRegistry`
per le Resource moderne, e la registrazione esplicita
`ColumnFunctionRegistry::allow('nomeCustom')` per le pagine legacy custom
dei siti (vedi la nota di migrazione in
`docs/app/concetti/tabelle/legacy.md`).

### 5. Legacy wrapper e migrazione interna

- `app/function/backend/plugin.php`: `active()`, `visible()`, `evidence()`,
  `returnBadge()` diventano wrapper deprecati che delegano a `BooleanBadge`.
  Leggono `$NAME` via `LegacyGlobals::get('NAME')` con fallback safe: se
  assente, nessun warning e action vuota. Firma e shape dell'oggetto di
  ritorno (proprietà `badge`, `tooltip`, `automaticResize`, `button`,
  `action`, ...) restano identiche.
- Migrazione delle Resource del framework ai nuovi helper:
  `UserManagementResource`, `LegalDocumentResource`, `CssFontResource`,
  `PopupResource`, `AnnouncementResource`.

### 6. Documentazione (richiesta esplicita dell'utente)

- `docs/app/concetti/tabelle/tablecolumn.md`: nuova sezione sui badge booleani
  (helper semantici, base generica, clickable opt-in, descrittore).
- `docs/app/concetti/tabelle/legacy.md`: nota di deprecazione per
  `->function('active'|'visible'|'evidence')` e per le funzioni di plugin.php,
  con mappa di migrazione.
- Verificare `SUMMARY.md` se serve una voce nuova.

### 7. Error handling

- Preset sconosciuto o descrittore malformato → cella vuota, nessun warning.
- Valore non-'true' → stato off.
- Fallimento del pre-render resta non fatale (fallback AJAX esistente).

### 8. Test

Unit test PHPUnit in `tests/Backend/Table/Badge/`:

- preset: testi/icone/colori per on/off dei tre preset;
- varianti HTML (`badge`, `automaticResize`, `tooltip`, `button`) identiche
  all'output attuale di `returnBadge()`/`returnButton()`;
- `clickable` off di default, wrapping onclick quando forzato;
- rimappatura `function('active') → badge` in `Field` (formato risultante);
- whitelist: nome non dichiarato → cella vuota.

Validazione di integrazione: `php -l` sui file toccati; da un sito
(`rsvp-site`): `php forge update --local`, `php forge start`, verifica che la
lista eventi non produca warning e che badge + menu azioni funzionino.

## Fuori scope

- Rendering badge fuori dalle tabelle backend (valutato e scartato: YAGNI).
- Ricostruzione completa server-side del config dell'endpoint `list-table`
  (refactor più ampio; la whitelist copre il rischio immediato).
