# Refactor globale legacy

## Contesto reale del package

Nel package le variabili globali legacy non nascono in un solo punto.
La loro costruzione e diffusione oggi passa soprattutto da questi file:

- `wonder-image.php`
- `app/config/array/array.php`
- `app/config/array/path.php`
- `app/config/connection/connection.php`
- `app/config/app/default.php`
- `app/service/auth.php`
- `app/service/lang.php`
- `app/utility/backend/set-up.php`
- `app/utility/frontend/set-up.php`
- `class/Http/RouteDispatcher.php`
- `class/View/View.php`

La lettura e la mutazione si concentrano invece soprattutto in:

- `app/function/sql.php`
- `app/function/info.php`
- `app/function/user/auth.php`
- `app/function/user/user.php`
- `app/function/backend/input.php`
- `app/function/file/*`
- `class/App/UpdateRunner.php`
- `app/http/*`
- `app/build/src/*`

Metriche semplici rilevate nel package:

- `42` file usano `global $...`
- `11` file scrivono direttamente su `$GLOBALS[...]`
- `72` file impostano direttamente flag route/request come `$BACKEND`, `$FRONTEND`, `$PRIVATE`, `$PERMIT`
- `20` file mutano `$ALERT`
- `21` file impostano o riassegnano `$MYSQLI_CONNECTION` o `$mysqli`
- `19` file definiscono o riassegnano oggetti/config globali come `$PATH`, `$TABLE`, `$DB`, `$DEFAULT`

Nota importante:
il package prevede override esterni sotto `custom/`, ma in questo workspace quella cartella non esiste. Quindi l'analisi copre il core reale del package; la strategia resta comunque compatibile con override futuri.

## Diagnosi

### 1. Definizioni sparse

La stessa superficie globale viene costruita a strati:

- array/object bootstrap in `app/config/array/array.php`
- path e costanti in `app/config/array/path.php`
- connessioni e credenziali in `app/config/connection/connection.php`
- dati request in `app/config/app/default.php`
- auth runtime in `app/service/auth.php`
- dati frontend/backend in `app/service/lang.php`, `app/utility/backend/set-up.php`, `app/utility/frontend/set-up.php`
- route state in `class/Http/RouteDispatcher.php`

Il risultato e' che non c'e' un punto unico che dica:

- quali globali esistono davvero
- quali sono statiche
- quali sono mutabili
- quali sono solo alias legacy

### 2. Spazio globale con responsabilita' miste

Oggi nello stesso namespace convivono quattro categorie diverse:

#### Config e registry

- `ROOT`
- `APP_VERSION`
- `ROOT_APP`
- `ROOT_RESOURCES`
- `PATH`
- `DEFAULT`
- `PERMITS`
- `TABLE`
- `DB`
- `MAIL`
- `API`
- `ANALYTICS`
- `COLOR`
- `FONT`

#### Servizi condivisi

- `MYSQLI_CONNECTION`
- `mysqli`

#### Stato runtime di request/route/sessione

- `PAGE`
- `BACKEND`
- `FRONTEND`
- `PRIVATE`
- `PERMIT`
- `ROUTE_PARAMETERS`
- `ROUTE_META`
- `USER`
- `ALERT`
- `ERROR`

#### Dati derivati o proiezioni runtime

- `SEO`
- `SOCIETY`

#### Context di area

- `NAV_BACKEND`

Il problema non e' l'esistenza delle globali in se', ma il fatto che categorie diverse sono trattate nello stesso modo.

### 3. Side effect impliciti

Ci sono file che, appena inclusi, producono mutazioni globali non ovvie:

- `app/service/auth.php` imposta `$USER`
- `app/service/lang.php` imposta `$SOCIETY` e muta `$PATH`
- `app/utility/backend/set-up.php` costruisce `$NAV_BACKEND`
- `app/utility/frontend/set-up.php` ricarica `$SEO`
- `app/function/sql.php` usa e riassegna `$mysqli`

Questo rende difficile capire l'ordine corretto di bootstrap e rende fragile ogni refactor.

### 4. Duplicazione della conoscenza

Prima di questa refactor la lista delle globali note era duplicata in piu' punti:

- `wonder-image.php`
- `class/Http/RouteDispatcher.php`
- `class/View/View.php`

Ogni nuova variabile o alias richiedeva aggiornamenti manuali in piu' file.

### 5. Test e manutenzione difficili

Molti helper leggono direttamente `global $...`, quindi:

- il setup dei test diventa costoso
- il comportamento dipende dall'ordine di inclusione
- una piccola mutazione puo' rompere codice lontano
- i nuovi handler rischiano di rientrare nella legacy anche quando non serve

## Struttura target

L'obiettivo non e' eliminare subito le globali.
L'obiettivo e' confinare la loro gestione ai bordi del framework.

### Modello target

1. Un registry centrale conosce tutte le globali legacy supportate.
2. Il registry classifica ogni variabile per gruppo:
   - `config`
   - `service`
   - `runtime`
3. Il bootstrap continua a pubblicare le stesse globali legacy in `$GLOBALS`.
4. Le estensioni area-specifiche o custom passano dal registry invece che da liste duplicate.
5. Il codice nuovo preferisce:
   - dipendenze esplicite
   - dati passati al render
   - letture mirate dal registry solo ai bordi

### Classe introdotta

`class/App/LegacyGlobals.php`

Responsabilita':

- catalogo centrale delle globali legacy supportate
- metadati minimi per categoria e ruolo
- sync verso `$GLOBALS`
- gestione del runtime context per variabili area-specifiche o estensioni legacy
- punto unico per `capture()`, `share()`, `get()`, `scope()`, `section()`

Questo non introduce un container pesante.
E' solo un registry statico, molto vicino al modello attuale, ma ordinato.

## File creati o modificati

### Creati

- `class/App/LegacyGlobals.php`
- `docs/app/specifiche/variabili/refactor-globale-legacy.md`

### Modificati

- `wonder-image.php`
- `class/Http/RouteDispatcher.php`
- `class/View/View.php`
- `app/utility/backend/set-up.php`

## Migrazione graduale proposta

### Step 1. Centralizzare il catalogo delle globali

Fatto in questa refactor.

Il package ora ha un punto unico che descrive le globali note:

- nome
- gruppo
- natura

Questo elimina la duplicazione della lista.

### Step 2. Tenere il bootstrap legacy, ma con regia unica

Fatto in questa refactor.

`wonder-image.php`, router e view ora passano dal registry.
Il comportamento legacy resta invariato:

- le globali continuano a esistere
- i file inclusi legacy continuano a funzionare
- i template continuano a ricevere variabili estratte

### Step 3. Spostare le nuove mutazioni dal raw global al registry

Da fare progressivamente.

Regola pratica:

- nuovo codice: usare `LegacyGlobals::get()` o `LegacyGlobals::share()` ai bordi
- codice legacy esistente: lasciare `global $...` finche' non si tocca quel modulo

Esempi di candidati naturali:

- `app/utility/backend/set-up.php`
- `app/utility/frontend/set-up.php`
- `app/service/auth.php`
- `app/service/lang.php`

### Step 4. Ridurre le letture globali dentro gli helper

Da fare per modulo, non per tutto il progetto insieme.

Ordine consigliato:

1. SQL helper
2. auth/user helper
3. backend input helper
4. mail/file helper

Pattern consigliato:

- prima accettare un parametro opzionale esplicito
- se assente, fallback alla globale legacy

Esempio:

```php
function sqlSelect($table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*', ?mysqli $connection = null) {
    $connection ??= \Wonder\App\LegacyGlobals::get('mysqli');

    $SQL = new Wonder\Sql\Query($connection);

    return $SQL->Select($table, $condition, $limit, $order, $orderDirection, $attributes);
}
```

Questo non rompe la legacy ma permette al nuovo codice di smettere di dipendere dal global state.

### Step 5. Ridurre il surface area nei nuovi handler

Gia' si vede in `app/http/*` con `Wonder\View\View::make(...)`.

Direzione consigliata:

- handler nuovo: prepara dati espliciti
- view: riceve dati espliciti
- globale solo per compatibilita' del layout o di helper legacy ancora vivi

### Step 6. Isolare alias legacy veri

Gli alias da mantenere ma non propagare ulteriormente sono soprattutto:

- `mysqli`
- eventuali variabili custom esposte dal runtime context

`NAV_BACKEND` non rientra qui: non e' una globale core e non va trattata come alias di sistema.
La sua collocazione corretta e' nel context backend.

Nuovo codice:

- leggere dal servizio reale o dal registry
- evitare di creare nuovi alias globali arbitrari

## Esempi prima/dopo

### 1. Bootstrap runtime

Prima:

```php
$GLOBALS['ROOT'] = $ROOT;
$GLOBALS['ROOT_APP'] = $ROOT_APP;
$GLOBALS['FRONTEND'] = $FRONTEND;
$GLOBALS['BACKEND'] = $BACKEND;
```

Dopo:

```php
\Wonder\App\LegacyGlobals::share([
    'ROOT' => $this->root,
    'ROOT_APP' => $this->runtimeRoot(),
    'FRONTEND' => $isFrontend,
    'BACKEND' => $isBackend,
]);
```

Stesso effetto legacy, ma con un solo entry point.

### 2. Area bootstrap

Prima:

```php
$GLOBALS['NAV_BACKEND'] = $NAV_BACKEND;
```

Dopo:

```php
\Wonder\App\LegacyGlobals::share([
    'NAV_BACKEND' => $NAV_BACKEND,
]);
```

Questo non la promuove a globale core.
La rende solo disponibile come context runtime dell'area backend.

### 3. Nuovo codice applicativo

Prima:

```php
global $PATH;
global $USER;
```

Dopo:

```php
$PATH = \Wonder\App\LegacyGlobals::get('PATH');
$USER = \Wonder\App\LegacyGlobals::get('USER');
```

Meglio ancora, quando possibile:

```php
function renderProfile(object $path, object $user): string
{
    // ...
}
```

## Perche' questa soluzione e non altre

### Scartata: rimozione diretta di tutte le globali

Troppo rischiosa.
Il package ha ancora molto codice `app/function/*`, `app/build/src/*` e template che dipendono da quell'interfaccia.
Sarebbe un big bang rewrite con regressioni quasi certe.

### Scartata: container DI completo

Sovradimensionato per il problema attuale.
Qui serve prima riordinare il bordo legacy, non imporre un framework architetturale pesante.

### Scartata: lasciare tutto com'e' e solo documentare

Insufficiente.
Il problema principale e' la duplicazione della regia delle globali.
Senza un punto unico il costo di manutenzione continua a crescere.

### Scelta: registry statico leggero + compatibilita' totale

Perche':

- mantiene tutte le globali legacy richieste
- non rompe handler, helper e template esistenti
- riduce la duplicazione
- prepara migrazioni locali e progressive
- resta facile da leggere tra sei mesi

## Rischi e mitigazioni

### Rischio 1. Custom bootstrap o override esterni

Se un progetto usa file in `custom/` che espongono nuove variabili, devono passare dal runtime context.

Mitigazione:

- `LegacyGlobals::share()` accetta anche chiavi non catalogate
- il router continua a propagare il runtime context ai handler e alle view

### Rischio 2. Codice che dipende dall'ordine di inclusione

E' gia' un rischio preesistente.

Mitigazione:

- mantenere il bootstrap invariato
- usare il registry solo come regia, non come inversione completa del flusso

### Rischio 3. Nuovo codice che continua ad aggiungere globali raw

Mitigazione:

- convenzione semplice: nessuna nuova scrittura diretta su `$GLOBALS[...]`
- se serve una globale legacy nuova, si registra in `LegacyGlobals`

### Rischio 4. Refactor troppo ampio dei helper

Mitigazione:

- non toccare tutto
- convertire per modulo
- prima parametri opzionali espliciti, poi riduzione delle `global`

## Convenzioni operative consigliate da qui in avanti

1. Definire o pubblicare globali legacy solo dal bootstrap e dal registry.
2. Per nuovo codice applicativo, preferire parametri espliciti.
3. Se serve leggere una globale legacy fuori dal bootstrap, usare `LegacyGlobals::get()`.
4. Se serve esporre stato runtime aggiuntivo a handler/view/layout, usare `LegacyGlobals::share()`.
5. Ogni nuova globale supportata va registrata in `LegacyGlobals`, con gruppo coerente.

## Risultato atteso

Con questa direzione il progetto converge verso:

- configurazione centralizzata
- servizi distinguibili dallo stato runtime
- compatibilita' legacy preservata
- bootstrap piu' prevedibile
- crescita della libreria senza aumentare il caos nel namespace globale
