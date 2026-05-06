# Progetto Sistema Moduli

## Obiettivo del progetto

Introdurre nel framework Wonder un sistema moduli esplicito, validabile e mantenibile che permetta di:

- creare moduli interni o esterni;
- installare, abilitare, disabilitare, aggiornare e rimuovere moduli;
- integrare moduli AI-generated con un contratto stabile e documentazione corta;
- riusare le convenzioni reali del framework invece di aggiungere una seconda architettura parallela.

Il sistema deve funzionare bene per moduli come:

- ecommerce;
- RSVP / eventi;
- blog;
- real estate;
- booking;
- cataloghi;
- membership;
- landing pages verticali;
- moduli generati da AI e poi presi in carico da sviluppatori.

## Stato attuale del framework

### 1. Struttura attuale

Il repository e' un package Composer (`wonder-image/app`), non un'app standalone.

Punti strutturali osservati:

- bootstrap package: `wonder-image.php`
- runtime legacy: `app/`
- architettura nuova: `class/App/*`
- routes package: `app/config/routes/*.php`
- handler backend/api: `app/http/...`
- view/layout package: `app/view/...`
- assets package: `resources/assets/...`
- traduzioni package: `resources/lang/...`
- documentazione GitBook: `docs/app/...` con indice `docs/app/SUMMARY.md`

### 2. Stack usato

- PHP `^8.2`
- Composer package type `library`
- autoload PSR-4: `Wonder\\ => class/`
- Symfony components presenti:
  - `symfony/console`
  - `symfony/dependency-injection`
  - `symfony/http-foundation`
  - `symfony/routing`
- runtime principale ancora custom/legacy, non full Symfony framework

### 3. Convenzioni esistenti

Le convenzioni correnti sono coerenti e vanno riusate:

- `Model` in `class/App/Models`
- `Resource` in `class/App/Resources`
- estensioni consumer in `ROOT/custom/...`
- route dichiarative in `config/routes/route.*.php`
- permessi globali in `app/config/app/permission.php` + merge da `custom/config/permissions.php`
- aggiornamenti via `UpdateRunner`
- schema SQL da `Model::tableSchema()`
- data handling da `Model::dataSchema()`
- backend CRUD/API CRUD auto-generati da `ResourceRegistry` + `ResourceRouteRegistrar`

### 4. Sistema di routing attuale

Il routing reale oggi usa:

- `class/Http/RouteDispatcher.php`
- `class/Http/Route.php`
- `class/Http/Router.php`
- files `route.*.php`

Le route vengono caricate da:

- `app/config/routes`
- `ROOT/custom/config/routes`

Backend e API package sono gia' registrati in:

- `app/config/routes/route.backend.php`
- `app/config/routes/route.api.php`

Le `Resource` nuove registrano automaticamente:

- backend CRUD HTML
- API CRUD JSON

Manca oggi un punto standard per caricare route da moduli.

### 5. Gestione database / migrations / models attuale

La gestione dati oggi e' ibrida ma chiara:

- `ModelRegistry` scopre i model
- `Model::tableSchema()` definisce lo schema SQL
- `UpdateRunner` sincronizza le tabelle
- `UpdateRunner` esegue file imperativi in:
  - `build/row`
  - `build/update`
  - `build/cli`

Osservazioni importanti:

- `build/table` esiste solo come compatibilita' legacy e non va usato per nuovi moduli;
- il vero punto moderno per lo schema tabellare sono i `Model`;
- i file `build/update` attuali vengono inclusi a ogni update e devono essere idempotenti.

### 6. Gestione frontend, componenti, layout e assets attuale

Frontend e backend usano:

- view package in `app/view`
- layout risolti da `class/View/View.php`
- utility legacy in `app/utility/backend/*` e `app/utility/frontend/*`
- assets package in `resources/assets`
- traduzioni in `resources/lang`

Le view del consumer possono fare override in:

- `custom/view/layout/...`

Il framework non ha oggi un sistema namespace-based per view di moduli. Le view vengono richiamate per path reale.

### 7. Auth / permessi / ruoli

Un sistema esiste gia', ma non e' centralizzato in classi moderne:

- definizioni permessi in `app/config/app/permission.php`
- merge custom da `custom/config/permissions.php`
- autorizzazione runtime in `app/function/user/auth.php`
- servizi bootstrap auth in `app/service/auth.php`

Le authority sono stringhe come:

- `admin`
- `administrator`
- `api_internal_user`
- `api_public_access`

Le `Resource` moderne dichiarano i permessi via `PermissionSchema`.

### 8. Sistema API

Esiste ed e' attivo:

- route API package in `app/config/routes/route.api.php`
- handler API package in `app/http/api/...`
- pattern moderno con `Wonder\Api\Handler`, `Endpoint`, `Response`
- CRUD API automatico per `Resource`

### 9. Eventi / hooks / middleware

Esiste qualche hook specifico, ma non un event bus generale.

Hook reali presenti:

- `Resource::afterStore()`
- `Resource::afterUpdate()`
- `Resource::afterDelete()`
- hook auth/authority in `app/function/user/*`
- pipeline auth federata in `class/Auth/Federated/*`

Middleware oggi:

- bootstrap minimale in `app/middleware/middleware.php`
- error middleware legacy

Conclusione:

- esistono hook puntuali;
- non esiste ancora un sistema moduli/event bus riusabile.

### 10. Sistema configurazione

La configurazione attuale e' basata su:

- `.env`
- file PHP in `app/config/...`
- override consumer in `custom/config/...`
- globali runtime catturate da `LegacyGlobals`

Non esiste oggi un config repository moderno per moduli.

### 11. Struttura documentazione attuale

La documentazione viva e' in:

- `docs/app/`

Pagine gia' utili:

- `docs/app/app/routing.md`
- `docs/app/app/api.md`
- `docs/app/app/layout.md`
- `docs/app/backend/resource-crud-dinamico.md`
- `docs/app/backend/resource-manuale/*`

### 12. GitBook / docs

Il progetto usa gia' un GitBook con:

- `docs/app/SUMMARY.md`

La proposta moduli deve quindi stare nella stessa struttura.

## Sintesi dei vincoli architetturali

Per rimanere compatibile col framework esistente, il sistema moduli deve:

- rispettare il package/consumer split;
- estendere `ModelRegistry`, `ResourceRegistry`, `UpdateRunner` e routing dichiarativo;
- riusare convenzioni `config/routes`, `resources/assets`, `resources/lang`, `view`, `class`;
- evitare una nuova mini-app o un secondo framework dentro il framework;
- essere leggibile anche da AI con pochi file guida.

## Proposta architetturale

### Principio base

Un modulo e' un pacchetto autodescrittivo con:

- manifest obbligatorio;
- struttura file standard;
- punti d'integrazione espliciti;
- lifecycle gestito dal framework;
- validazione preventiva;
- stato separato tra discovery, configurazione desiderata e stato applicato.

### Decisione chiave

Il sistema moduli proposto non sostituisce `Model + Resource`.

Al contrario:

- un modulo usa `Model + Resource` per dati, backend e API;
- usa route file standard per pagine non-CRUD;
- usa `build/update` e `build/row` come lifecycle/migrazioni idempotenti;
- aggiunge solo il layer mancante: discovery, manifest, registry, stato e validazione.

### Modello concettuale

Si introducono 5 layer:

1. `ModuleDiscovery`
   - trova i moduli disponibili.

2. `ModuleManifest`
   - legge e valida `module.json`.

3. `ModuleRegistry`
   - mantiene l'elenco dei moduli scoperti, validi e risolti per dipendenze.

4. `ModuleRuntime`
   - registra config, permissions, routes, models, resources, assets, locales e services dei moduli abilitati.

5. `ModuleLifecycle`
   - gestisce install, update, uninstall, enable, disable e tracking versioni.

### Linea architetturale fissata

Prendendo `wonder-image/rsvp` come riferimento, la linea da fissare e' questa:

- il modulo canonico e' un package Composer autonomo;
- il root canonico del modulo e' la root del package;
- il namespace base standard e' `Wonder\\Plugin\\`;
- il modulo espone un entrypoint PHP unico oltre al manifest;
- il consumer non deve piu' integrare il modulo copiando stub manuali in `custom/...`.

Questa linea e' preferibile a `ROOT/modules/*` come formato principale perche':

- e' gia' coerente con `wonder-image/rsvp`;
- rende naturale versioning, release e changelog per modulo;
- usa Composer come meccanismo di installazione, che e' gia' lo standard del progetto;
- separa meglio codice del modulo e codice del consumer.

`ROOT/modules/*` puo' restare come supporto secondario per sviluppo locale, non come formato ufficiale.

## Struttura cartelle proposta

La struttura deve adattarsi alle convenzioni reali del repo. Per questo la proposta migliore e' una struttura che replica i nomi gia' usati nel framework.

### Root di discovery proposti

1. package Composer esterni installati in `vendor/*/*`
   - formato principale e raccomandato.

2. package bundled dal core, se mai introdotti
   - sempre nello stesso formato package-root.

3. `ROOT/modules/*`
   - opzionale, solo per sviluppo locale o prototipi non ancora pubblicati.

La discovery non deve essere "per nome cartella", ma "per package root che contiene `module.json`".

### Struttura standard modulo

```text
wonder-image/blog/
  composer.json
  module.json
  README.md
  CHANGELOG.md
  src/
    Blog.php
    Models/
    Resources/
    Services/
    Support/
    Hooks/
  config/
    module.php
    permissions.php
    routes/
      route.frontend.php
      route.backend.php
      route.api.php
  build/
    update/
    row/
    cli/
    install.php
    uninstall.php
  handlers/
    frontend/
    backend/
    api/
  views/
    frontend/
    backend/
    components/
  resources/
    assets/
  lang/
  tests/
  examples/
```

### Note sulla struttura

- `src/Models` e `src/Resources` seguono la semantica del core, ma sotto namespace modulo.
- `src/<Module>.php` e' l'entrypoint unico del modulo, nello stile di `Wonder\Plugin\Rsvp\Rsvp`.
- `config/routes/route.*.php` riusa la semantica attuale del router.
- `build/update`, `build/row`, `build/cli` riusano il lessico del framework.
- `handlers/` standardizza gli entrypoint HTTP custom del modulo.
- `views/` riusa il sistema view corrente.
- `lang/` segue il pattern gia' usato da `wonder-image/rsvp`.
- `resources/assets` mantiene il pattern del core per gli asset statici.
- `build/table` non e' previsto.

### Namespace standard

Il namespace base standard del sistema moduli deve essere:

```php
Wonder\Plugin\
```

Esempi:

- `Wonder\Plugin\Blog\Blog`
- `Wonder\Plugin\Blog\Models\Post`
- `Wonder\Plugin\Blog\Resources\PostResource`
- `Wonder\Plugin\Rsvp\Rsvp`

## Contratto standard di un modulo

Ogni modulo valido deve dichiarare e rispettare almeno:

- `composer.json` coerente col namespace;
- `module.json` obbligatorio;
- slug univoco;
- versione semantica;
- compatibilita' con framework/PHP;
- dipendenze da altri moduli;
- path standard o dichiarati nel manifest;
- almeno un entrypoint utile tra:
  - `class/Resources`
  - `config/routes`
  - `class/Services`
  - `build/update`
- README modulo;
- changelog modulo.

### File obbligatori minimi v1

- `composer.json`
- `module.json`
- `README.md`
- `CHANGELOG.md`

### File opzionali ma standardizzati

- `config/module.php`
- `config/permissions.php`
- `config/routes/route.*.php`
- `src/<Module>.php`
- `src/Models/*`
- `src/Resources/*`
- `src/Services/*`
- `build/update/*`
- `build/row/*`
- `handlers/*`
- `views/*`
- `lang/*`
- `resources/assets/*`
- `tests/*`

## Manifest del modulo

### Formato proposto

Formato consigliato: `module.json`

Motivi:

- leggibile da AI senza parsing di PHP;
- validabile con JSON Schema;
- abbastanza semplice da essere editato a mano;
- compatibile con discovery locale e via Composer.

### Esempio manifest

```json
{
  "name": "Wonder Blog",
  "slug": "blog",
  "version": "1.0.0",
  "description": "Modulo blog con frontend, backend e API.",
  "namespace": "Wonder\\Plugin\\Blog\\",
  "entrypoint": "Wonder\\Plugin\\Blog\\Blog",
  "author": {
    "name": "Wonder Image",
    "email": "info@wonderimage.it"
  },
  "frameworkCompatibility": {
    "wonder-app": "^2.1",
    "php": "^8.2"
  },
  "dependencies": {
    "modules": [],
    "composer": []
  },
  "paths": {
    "src": "src",
    "config": "config",
    "routes": "config/routes",
    "build": "build",
    "handlers": "handlers",
    "views": "views",
    "assets": "resources/assets",
    "lang": "lang",
    "tests": "tests"
  },
  "permissions": {
    "definitions": "config/permissions.php"
  },
  "routes": {
    "frontend": "config/routes/route.frontend.php",
    "backend": "config/routes/route.backend.php",
    "api": "config/routes/route.api.php"
  },
  "database": {
    "models": "src/Models",
    "update": "build/update",
    "row": "build/row",
    "install": "build/install.php",
    "uninstall": "build/uninstall.php"
  },
  "hooks": {
    "providers": [
      "Wonder\\Plugin\\Blog\\Hooks\\BlogHooks"
    ]
  },
  "configSchema": {
    "defaults": "config/module.php",
    "schema": "config/module.schema.json"
  },
  "exposedComponents": [],
  "exposedServices": [
    "blog.repository",
    "blog.publisher"
  ],
  "lifecycle": {
    "install": "build/install.php",
    "uninstall": "build/uninstall.php"
  }
}
```

### Campi minimi richiesti

- `name`
- `slug`
- `version`
- `description`
- `namespace`
- `entrypoint`
- `author`
- `frameworkCompatibility`
- `dependencies`
- `paths`

### Campi fortemente consigliati

- `routes`
- `database`
- `permissions`
- `configSchema`
- `hooks`
- `exposedServices`
- `exposedComponents`
- `lifecycle`

### Validazione manifest

Il manifest dovra' essere validato da:

- JSON Schema machine-readable;
- validatore PHP runtime;
- comando CLI dedicato.

Validazioni obbligatorie:

- file JSON valido;
- slug lowercase kebab-case univoco;
- namespace PSR-4 sotto `Wonder\\Plugin\\`;
- entrypoint esistente e autoloadabile;
- versione semver valida;
- compatibilita' `wonder-app` soddisfatta;
- compatibilita' PHP soddisfatta;
- dipendenze moduli esistenti;
- path dichiarati esistenti;
- assenza di riferimenti fuori root modulo;
- assenza di collisioni con altri moduli:
  - slug;
  - authority;
  - service id;
  - route name prefix se dichiarato.

## Registry e loader moduli

### Componenti proposti

#### `ModuleDiscovery`

Responsabilita':

- scansiona root note;
- legge i manifest;
- scopre moduli Composer dichiarati via `extra.wonder.module`;
- restituisce candidati.

#### `ModuleManifestValidator`

Responsabilita':

- valida schema;
- valida compatibilita';
- valida file/path;
- produce errori leggibili.

#### `ModuleRegistry`

Responsabilita':

- tiene l'elenco moduli scoperti;
- risolve dipendenze;
- espone moduli abilitati/disabilitati/non validi;
- espone errori strutturati.

#### `ModuleStateRepository`

Responsabilita':

- legge stato desiderato dal file config consumer;
- legge stato applicato dal database;
- confronta versione manifest e versione installata.

### Stato desiderato vs stato applicato

Per evitare ambiguita', separare due livelli:

1. stato desiderato
   - file versionato nel consumer, per esempio `custom/config/modules.php`
   - indica moduli abilitati, disabilitati, configurazione e override

2. stato applicato
   - tabella DB del framework, proposta `app_module_states`
   - salva versione installata, hash manifest, timestamp install/update, stato lifecycle

Questa separazione e' importante per:

- deploy ripetibili;
- CI/CD;
- `forge update --local`;
- rollback leggibili;
- diagnosi di mismatch tra file e database.

### Ordine di bootstrap proposto

Durante il bootstrap package:

1. carica config core;
2. scopre i moduli;
3. valida i manifest;
4. legge `custom/config/modules.php`;
5. risolve modulo abilitati;
6. merge config modulo;
7. merge permissions modulo;
8. registra dirs model/resource modulo;
9. registra services/hooks modulo;
10. registra routes modulo;
11. espone assets/lang/views modulo.

## Gestione configurazioni modulo

### Strategia proposta

Per coerenza con il framework:

- default modulo in `config/module.php`
- override consumer in `custom/config/modules/<slug>.php`
- stato abilitazione in `custom/config/modules.php`

Il package modulo dovrebbe esporre helper statici di path, per esempio:

- `Blog::root()`
- `Blog::handlerPath(...)`
- `Blog::viewPath(...)`
- `Blog::langPath()`

### Esempio file stato moduli

```php
<?php

return [
    'blog' => [
        'enabled' => true,
        'config' => [
            'posts_per_page' => 12,
            'allow_comments' => false,
        ],
    ],
    'rsvp' => [
        'enabled' => false,
        'config' => [],
    ],
];
```

### Regole

- il manifest descrive il contratto;
- il file consumer decide l'abilitazione;
- il config schema descrive i valori ammessi;
- il framework deve validare gli override consumer.

## Gestione routes

### Direzione proposta

Non introdurre un router nuovo.

Integrare invece i moduli dentro il router esistente:

- aggiungere un file package `app/config/routes/route.frontend.php`;
- dentro i file route package, chiamare un `ModuleRouteRegistrar`;
- il registrar includera' i `route.*.php` dei moduli abilitati.

### Convenzioni route modulo

Ogni modulo potra' dichiarare opzionalmente:

- `config/routes/route.frontend.php`
- `config/routes/route.backend.php`
- `config/routes/route.api.php`

### Regole

- le route restano dichiarative via `Wonder\Http\Route`;
- le route CRUD standard continuano a nascere dalle `Resource`;
- le route custom modulo servono per frontend, wizard, webhook, export, dashboard speciali;
- il modulo non deve scrivere file `handler` o toccare `.htaccess`.
- i file route modulo devono referenziare handler tramite entrypoint statico del modulo, non tramite path hardcoded sparsi nel consumer.

## Gestione migrations / database

### Principio

Per i moduli nuovi:

- lo schema strutturale deve stare nei `Model`;
- le migrazioni imperative devono stare nel modulo;
- il runner deve tracciare cosa e' stato gia' eseguito.

### Strategia proposta

1. `src/Models`
   - fonte primaria per struttura tabelle.

2. `build/update`
   - migrazioni o trasformazioni dati idempotenti e versionate.

3. `build/row`
   - bootstrap dati, seed o record richiesti dal modulo.

4. `build/install.php`
   - operazioni one-shot di install.

5. `build/uninstall.php`
   - pulizia controllata.

### Tracciamento proposto

Introdurre due tabelle tecniche:

- `app_module_states`
- `app_module_migrations`

`app_module_migrations` deve tracciare almeno:

- slug modulo;
- fase (`install`, `update`, `row`, `uninstall`);
- file;
- hash file;
- esito;
- timestamp.

### Regola importante

I file `build/update` di modulo non devono piu' essere inclusi sempre in blind.
Devono essere eseguiti dal runner modulo con tracking per file/hash.

## Gestione permessi

### Direzione proposta

Non introdurre un sistema RBAC separato.

Riutilizzare il sistema authority attuale con un layer modulo sopra.

### Convenzione

Ogni modulo puo' fornire:

- `config/permissions.php`

Questo file deve restituire un array compatibile con `$CUSTOM_PERMITS`.

### Regole

- le authority devono essere prefissate dal modulo:
  - `blog_admin`
  - `blog_editor`
  - `rsvp_manager`
- il manifest deve dichiarare i permessi forniti per validazione e DX;
- le `Resource` e le route custom useranno quelle authority nei `permit(...)` o `PermissionSchema`.

## Gestione assets / frontend

### Direzione proposta

Per v1 evitare build pipeline pesanti.

Supporto iniziale:

- raw CSS/JS/images da `resources/assets`;
- publish/copy degli assets modulo durante `forge update --local` o comando dedicato;
- output in `ROOT/assets/<ASSETS_VERSION>/modules/<slug>/...`.

### Regole

- niente bundler obbligatorio in v1;
- niente compilazioni JS complesse come prerequisito del core;
- naming sempre namespaced per modulo;
- view frontend del modulo preferibilmente riusano layout esistenti.

### View e componenti

Poiche' `View::layout()` oggi risolve solo core/custom:

- v1: i moduli devono riusare layout esistenti (`backend.main`, `frontend.main`, ecc.);
- le view del modulo possono essere richiamate per path assoluto tramite helper/registry;
- il supporto a layout namespaced di modulo e' opzionale e non prioritario in v1.

Pattern consigliato:

- `Wonder\Plugin\<Module>\<Module>::viewPath('frontend/home.php')`
- `Wonder\Plugin\<Module>\<Module>::viewPath('backend/post/list.php')`

## Gestione API

### Strategia

Le API modulo devono seguire due percorsi:

1. CRUD standard
   - via `Resource` + `ResourceRouteRegistrar`

2. endpoint custom
   - via `config/routes/route.api.php` + handler in stile `Wonder\Api\Handler`

### Regole

- response format coerente con API esistente;
- auth coerente con authority API esistenti;
- endpoint custom dichiarati chiaramente nel manifest.

## Gestione eventi / hooks

### Stato attuale

Il framework non ha un event bus generico.

### Proposta v1

Introdurre un hook system semplice e sincrono, non un event dispatcher pesante.

Punti hook minimi:

- `module.register`
- `module.boot`
- `module.installing`
- `module.installed`
- `module.updating`
- `module.updated`
- `module.uninstalling`
- `module.uninstalled`

### Proposta tecnica

- provider classe opzionale dichiarata nel manifest;
- interfaccia leggera, per esempio `ModuleProviderInterface`;
- registrazione hook tramite classi PHP del modulo.

### Scelta esplicita

Non introdurre subito `symfony/event-dispatcher`.
Il beneficio oggi non giustifica la complessita' aggiuntiva.

## Flusso di installazione modulo

### Flusso proposto

1. discovery modulo
2. validazione manifest
3. validazione compatibilita'
4. risoluzione dipendenze
5. registrazione stato desiderato in `custom/config/modules.php`
6. `forge install-module <slug>` oppure `forge enable-module <slug>`
7. `UpdateRunner` esegue:
   - install script
   - sync tabelle da model
   - update scripts tracciati
   - row scripts tracciati
   - publish assets
8. salvataggio stato applicato in `app_module_states`
9. bootstrap successivo espone routes, permissions, config, resources e servizi del modulo

### Regole

- install fallisce se mancano dipendenze;
- install fallisce se manifest non valido;
- install fallisce se due moduli collidono su slug/authority/service id;
- messaggi di errore sempre leggibili.

## Flusso di disinstallazione modulo

### Flusso proposto

1. verifica reverse dependencies
2. se altri moduli dipendono da quello, blocco uninstall
3. disabilitazione modulo
4. esecuzione opzionale `build/uninstall.php`
5. eventuale rimozione assets pubblicati
6. aggiornamento stato applicato

### Regola di sicurezza

Default sicuro:

- uninstall senza drop dati

Opzione esplicita:

- `--drop-data`

Il framework non deve eliminare tabelle o file senza consenso esplicito.

## Flusso di aggiornamento modulo

### Flusso proposto

1. rileva nuova versione manifest
2. confronta con `app_module_states`
3. valida compatibilita'
4. esegue schema sync da `Model`
5. esegue update scripts non ancora applicati
6. aggiorna assets pubblicati
7. aggiorna stato versione e hash

### Regole

- update idempotente;
- update interrotto deve lasciare log chiaro;
- release mismatch tra file e DB deve essere visibile in CLI.

## Gestione dipendenze tra moduli

### Tipi di dipendenza

1. hard dependency
   - modulo richiesto per funzionare.

2. soft dependency
   - integrazione opzionale.

3. versioned dependency
   - vincolo su range versione.

### Regole proposte

- solo hard dependency in v1 per semplicità runtime;
- soft dependency supportabile solo come metadata/documentazione;
- risoluzione topologica prima di install/enable;
- errore se ciclo dipendenze.

## Developer Experience

### Comandi CLI consigliati

Da introdurre solo in fase implementativa:

- `php forge make:module Blog`
- `php forge validate:module blog`
- `php forge install:module blog`
- `php forge enable:module blog`
- `php forge disable:module blog`
- `php forge uninstall:module blog`
- `php forge status:modules`
- `php forge test:module blog`

### Coerenza con stack attuale

La naming convention migliore e' restare dentro `forge` e dentro il pattern dei comandi gia' esistenti.

### Scaffold modulo v1

Lo scaffold deve generare:

- package Composer `wonder-image/<slug>`;
- namespace `Wonder\Plugin\<StudlySlug>\`;
- manifest minimo;
- README;
- CHANGELOG;
- config base;
- cartelle standard;
- esempio di `Model` e `Resource` opzionali;
- test placeholder;
- checklist finale.

## Testing

### Stato attuale

Il repo oggi non ha un test suite automatica configurata.

### Proposta

Il sistema moduli deve introdurre testing su due livelli:

1. smoke validation minima
   - `php -l`
   - validazione manifest
   - validazione dependency graph
   - `composer dumpautoload`

2. test automatizzati del nuovo subsystem
   - raccomandato introdurre PHPUnit come dev dependency nella fase implementativa

### Test minimi da coprire

- manifest validator
- discovery locale
- discovery da Composer metadata
- dependency resolver
- enable/disable state
- route registration modulo
- merge permissions modulo
- lifecycle tracking update/install

### Test di integrazione consigliati

- `php forge update --local` in consumer project
- verifica bootstrap con modulo abilitato
- verifica errori chiari con modulo invalido

## Documentazione GitBook da aggiungere

Questa proposta raccomanda una nuova sezione:

```text
docs/app/modules/
  README.md
  module-system.md
  module-contract.md
  module-manifest.md
  module-lifecycle.md
  module-routing.md
  module-database.md
  module-permissions.md
  module-frontend.md
  module-events-hooks.md
  module-testing.md
  module-release-checklist.md
  ai-module-generation-guide.md
  AI_CONTEXT.md
  MODULE_CONTRACT.md
  MODULE_CHECKLIST.md
  MODULE_MANIFEST_SCHEMA.md
  examples/
    hello-module.md
    blog-module.md
    rsvp-module.md
    real-estate-module.md
```

### File single source of truth per AI

Questi devono essere i 4 file principali:

- `docs/app/modules/AI_CONTEXT.md`
- `docs/app/modules/MODULE_CONTRACT.md`
- `docs/app/modules/MODULE_CHECKLIST.md`
- `docs/app/modules/MODULE_MANIFEST_SCHEMA.md`

Obiettivo:

- una AI deve poter leggere 2-4 file e produrre un modulo corretto.

## Guida AI da prevedere

La futura pagina `docs/app/modules/ai-module-generation-guide.md` dovra' includere:

- regole obbligatorie;
- struttura standard;
- manifest minimo;
- lifecycle minimo;
- pattern corretti;
- pattern vietati;
- checklist finale;
- prompt template;
- esempio minimo completo;
- errori comuni;
- criteri di integrazione corretta.

## Rischi tecnici

### Rischio 1

Il framework oggi e' ibrido legacy/moderno.

Impatto:

- se il sistema moduli ignora il runtime legacy, l'integrazione reale fallira'.

Mitigazione:

- progettare moduli come estensione di `ModelRegistry`, `ResourceRegistry`, `UpdateRunner`, routes e config esistenti.

### Rischio 2

Le view modulo non hanno oggi un namespace resolver nativo.

Mitigazione:

- v1 riusa layout esistenti e usa path reali per le view modulo.

### Rischio 3

I `build/update` attuali non hanno tracking per file.

Mitigazione:

- introdurre tracking modulo per update/install/row scripts.

### Rischio 4

Scansione indiscriminata di `vendor/` sarebbe costosa e fragile.

Mitigazione:

- discovery Composer solo via metadata espliciti `extra.wonder.module`.

### Rischio 5

Collisioni authority/routes/services tra moduli.

Mitigazione:

- validazione preventiva e naming namespaced.

## Decisioni da prendere prima dell'implementazione

1. root ufficiale moduli canonici:
   - raccomandato package Composer autonomo
   - esempio `vendor/wonder-image/rsvp/`

2. root locale di sviluppo:
   - supporto opzionale a `ROOT/modules/*`

3. persistenza stato desiderato:
   - raccomandato `custom/config/modules.php`

4. persistenza stato applicato:
   - raccomandato DB table `app_module_states`

5. test framework:
   - introdurre o no PHPUnit come nuova dev dependency

6. publish assets:
   - copia su `assets/<version>/modules/<slug>` oppure accesso diretto
   - raccomandata copia/pubblicazione

7. supporto layout namespaced di modulo in v1:
   - raccomandato no, per restare semplici

8. lifecycle uninstall:
   - default raccomandato `keep-data`

9. naming package/namespace:
   - raccomandato `wonder-image/<slug>` + `Wonder\Plugin\<StudlySlug>\`

## Piano di implementazione diviso in step piccoli

### Step 1

Creare il dominio moduli minimo:

- classi `ModuleManifest`, `ModuleDiscovery`, `ModuleRegistry`, `ModuleStateRepository`
- definizione dell'entrypoint contract modulo

### Step 2

Definire JSON Schema manifest e validatore CLI.

### Step 3

Aggiungere root ufficiali di discovery:

- package Composer con metadata espliciti
- root locale `ROOT/modules/*` solo per sviluppo

### Step 4

Introdurre file stato desiderato:

- `custom/config/modules.php`

### Step 5

Integrare moduli abilitati in:

- config loader
- permission merge
- model/resource discovery

### Step 6

Aggiungere registrazione routes modulo:

- backend
- api
- frontend

### Step 7

Aggiungere lifecycle persistence:

- `app_module_states`
- `app_module_migrations`

### Step 8

Integrare modulo runner dentro `UpdateRunner`.

### Step 9

Gestire publish assets/lang/views e helper path.

### Step 10

Aggiungere comandi CLI DX:

- make
- validate
- enable
- disable
- install
- uninstall
- status

### Step 11

Scrivere documentazione moduli per umani e AI.

### Step 12

Creare moduli esempio:

- `hello`
- `blog` oppure `rsvp`

## Criteri di accettazione

La futura implementazione del sistema moduli sara' accettabile se:

- esiste un manifest standard validabile;
- esiste discovery modulare con errori leggibili;
- i moduli possono essere abilitati/disabilitati senza patchare il core;
- routes, permissions, models e resources di modulo vengono caricati solo se il modulo e' abilitato;
- il lifecycle update/install/uninstall e' tracciato;
- le dipendenze tra moduli sono validate;
- il sistema non rompe `Model + Resource` esistenti;
- la documentazione moduli consente a una AI di creare un modulo leggendo pochi file;
- esistono almeno un modulo starter e un modulo esempio realistico;
- i comandi CLI principali funzionano nel consumer project.

## Raccomandazione finale

La soluzione migliore non e' inventare un "framework dentro il framework".

La soluzione migliore e':

- standardizzare un modulo come estensione dichiarativa del framework esistente;
- adottare come formato canonico un package Composer nello stile di `wonder-image/rsvp`;
- usare il namespace base `Wonder\Plugin\`;
- usare `module.json` come contratto;
- mantenere `Model + Resource + routes + update runner` come fondazione;
- aggiungere registry, validation, lifecycle e docs AI-first sopra quei mattoni.

## Implementation Checklist

- [ ] Definire i root ufficiali di discovery moduli
- [ ] Definire il formato canonico package Composer `wonder-image/<slug>`
- [ ] Definire il namespace canonico `Wonder\\Plugin\\<StudlySlug>\\`
- [ ] Definire l'entrypoint statico del modulo (`Blog::root()`, `handlerPath()`, `viewPath()`, `langPath()`)
- [ ] Definire il formato `module.json`
- [ ] Scrivere JSON Schema del manifest
- [ ] Implementare validatore manifest in PHP
- [ ] Implementare `ModuleDiscovery`
- [ ] Implementare `ModuleRegistry`
- [ ] Implementare risoluzione dipendenze e rilevazione cicli
- [ ] Definire `custom/config/modules.php` come stato desiderato
- [ ] Definire tabella `app_module_states`
- [ ] Definire tabella `app_module_migrations`
- [ ] Integrare i model di modulo nel `ModelRegistry`
- [ ] Integrare le resource di modulo nel `ResourceRegistry`
- [ ] Integrare merge config modulo
- [ ] Integrare merge permissions modulo
- [ ] Aggiungere `route.frontend.php` package come entrypoint frontend moduli
- [ ] Implementare `ModuleRouteRegistrar`
- [ ] Integrare route backend modulo
- [ ] Integrare route API modulo
- [ ] Integrare route frontend modulo
- [ ] Integrare lifecycle moduli in `UpdateRunner`
- [ ] Introdurre tracking per file `build/update`
- [ ] Introdurre tracking per file `build/row`
- [ ] Definire policy `install`, `enable`, `disable`, `uninstall`
- [ ] Implementare publish assets modulo
- [ ] Implementare load traduzioni modulo
- [ ] Definire helper/view strategy per file view modulo
- [ ] Implementare registry servizi/hooks modulo
- [ ] Aggiungere comando `forge make:module`
- [ ] Aggiungere comando `forge validate:module`
- [ ] Aggiungere comando `forge enable:module`
- [ ] Aggiungere comando `forge disable:module`
- [ ] Aggiungere comando `forge install:module`
- [ ] Aggiungere comando `forge uninstall:module`
- [ ] Aggiungere comando `forge status:modules`
- [ ] Aggiungere test unitari sul subsystem moduli
- [ ] Aggiungere test di integrazione su consumer project
- [ ] Scrivere `docs/app/modules/README.md`
- [ ] Scrivere `docs/app/modules/module-system.md`
- [ ] Scrivere `docs/app/modules/module-contract.md`
- [ ] Scrivere `docs/app/modules/module-manifest.md`
- [ ] Scrivere `docs/app/modules/module-lifecycle.md`
- [ ] Scrivere `docs/app/modules/module-routing.md`
- [ ] Scrivere `docs/app/modules/module-database.md`
- [ ] Scrivere `docs/app/modules/module-permissions.md`
- [ ] Scrivere `docs/app/modules/module-frontend.md`
- [ ] Scrivere `docs/app/modules/module-events-hooks.md`
- [ ] Scrivere `docs/app/modules/module-testing.md`
- [ ] Scrivere `docs/app/modules/module-release-checklist.md`
- [ ] Scrivere `docs/app/modules/ai-module-generation-guide.md`
- [ ] Scrivere `docs/app/modules/AI_CONTEXT.md`
- [ ] Scrivere `docs/app/modules/MODULE_CONTRACT.md`
- [ ] Scrivere `docs/app/modules/MODULE_CHECKLIST.md`
- [ ] Scrivere `docs/app/modules/MODULE_MANIFEST_SCHEMA.md`
- [ ] Creare modulo esempio `hello`
- [ ] Creare modulo esempio `blog` o `rsvp`
