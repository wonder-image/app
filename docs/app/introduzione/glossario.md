---
icon: book
---

# Glossario

Termini chiave del framework. I nomi tecnici (classi, metodi) restano in
inglese e invariati; la spiegazione è in italiano. Tra parentesi il file di
riferimento nel codice.

## Confine framework / sito

- **framework / app** — `wonder-image/app`, il core distribuito come pacchetto
  Composer. In un sito vive in `vendor/wonder-image/app/`.
- **site / new-site** — un progetto che dipende da `wonder-image/app`. Lo
  scaffold ufficiale è `wonder-image/new-site`.
- **lib** — `wonder-image/lib`, il design system JS/CSS (classi `.wi-*`),
  pacchetto npm `wonder-image`.
- **module** — pacchetto `wonder-image/<slug>` che aggiunge Model, Resource,
  route, traduzioni e permessi. Scoperto via Composer, abilitato dal sito in
  `custom/config/modules.php`.
- **`custom/`** — cartella del solo sito: pagine, componenti, route, config,
  helper. Non esiste dentro il framework.

## Model e Database

- **Model** — classe che rappresenta una tabella e ne gestisce dati e query
  (`class/App/Model.php`). Estende `Wonder\App\Model`.
- **`dataSchema()`** — array di `Field` che descrive i dati: validazione,
  formattazione, persistenza, generazione SQL.
- **`tableSchema()`** — array di colonne SQL (DDL): struttura della tabella.
  Tipicamente richiama `sqlColumnsFromDataSchema()`.
- **`sqlColumnsFromDataSchema()`** — genera le colonne SQL a partire dai
  `Field` di `dataSchema()`, per tenere DDL e dati allineati.
- **soft-delete / `$defaultCondition`** — filtro WHERE di default
  (`['deleted' => 'false']`) che esclude le righe cancellate logicamente.

## Resource e schema

- **Resource** — classe che espone un Model come CRUD backend/API
  (`class/App/Resource.php`). Dichiara form, tabella, permessi, navigazione, API.
- **`formSchema()`** — gli input del form backend (lista di `FormInput`).
- **`tableSchema()` (Resource)** — le colonne della lista (lista di
  `TableColumn`). Da non confondere con il `tableSchema()` del Model (DDL).
- **`permissionSchema()`** — quali authority possono fare quali azioni
  (`PermissionSchema`).
- **`navigationSchema()`** — voce di menu backend (`NavigationSchema`).
- **`apiSchema()`** — superficie API esposta e proiezione dei campi (`ApiSchema`).
- **`querySchema()`** — filtri/ordinamento/limite di default per la lista.

## Form

- **FormField** — classe base del DSL dei form
  (`class/App/ResourceSchema/FormField.php`). Espone i type-helper (`text()`,
  `select()`, `fileDragDrop()`, …).
- **FormInput** — sottoclasse usata in `formSchema()`: `FormInput::key('nome')`.
- **RepeaterColumn** — un campo dentro una riga ripetibile; estende `FormField`.
- **RepeaterRelation** — collega le righe di un repeater a una tabella correlata.
- **Theme (Wonder / Bootstrap)** — i due renderer: `Wonder` per il frontend
  (markup `.wi-*`), `Bootstrap` per il backend. Stesso `FormInput`, due rese.
- **FormFieldElementFactory** — il dispatcher che traduce un `FormField` nel
  componente concreto (`class/App/Support/FormFieldElementFactory.php`).

## Tabelle

- **TableColumn** — una colonna della lista backend
  (`class/App/ResourceSchema/TableColumn.php`). API corrente.
- **tableLayoutSchema** — la "cornice" della tabella: titolo, filtri, bottone
  aggiungi (`TableLayoutSchema`).
- **ResourceTableRenderer** — il ponte che converte i `TableColumn` nella
  resa concreta (`class/Backend/Support/ResourceTableRenderer.php`).
- **Table legacy** — la vecchia classe `Wonder\Backend\Table\Table`
  (`class/Backend/Table/Table.php`): non più API primaria, vedi
  [appendice legacy](../concetti/tabelle/legacy.md).

## Pagine non-CRUD

- **PageSchema** — definisce quali pagine CRUD sono attive (list, create,
  store, view, edit, update, delete).
- **CustomPageSchema** — base per pagine backend non-CRUD (login, profilo,
  dashboard): definisce solo input e label, non registra route da sola
  (`class/App/PageSchema/CustomPageSchema.php`).

## Permessi e utenti

- **area** — ambito di accesso: `frontend`, `backend`, `api`
  (`class/App/Permission/Area.php`).
- **authority** — ruolo/chiave permesso (es. `admin`, `administrator`,
  `client`, `api_internal_user`). Definita nel builder.
- **Permission / Permissions** — builder dei permessi
  (`class/App/Permission/{Permission,Permissions}.php`); registro in
  `app/config/app/permission.php`.
- **`$PERMITS`** — struttura finale esportata da `Permissions::toArray()`,
  letta a runtime da `permissions()`, `permissionsBackend()`, `permissionsApi()`.
- **UserManagementResource** — base condivisa per la gestione utenti
  backend/API (`class/App/Resources/Support/UserManagementResource.php`).

## Moduli

- **ModuleInterface** — contratto minimo di un modulo
  (`class/App/Module/Contracts/ModuleInterface.php`).
- **module.json** — manifest del modulo: nome, slug, namespace, entrypoint,
  compatibilità, dipendenze, percorsi.
- **Discovery / Registry** — scoperta e registrazione dei moduli
  (`class/App/Module/{Discovery,Registry}.php`).

## Piattaforma

- **ROOT** — radice del sito (risolta in `wonder-image.php`).
- **forge** — la CLI del framework (`php forge ...`), sorgente in
  `class/Console/Commands/*`.
- **ResourceRouteRegistrar** — genera automaticamente le route CRUD da ogni
  Resource registrata.
