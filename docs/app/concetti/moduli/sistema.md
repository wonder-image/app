---
icon: gears
---

# Sistema moduli

## Scoperta (Discovery)

`class/App/Module/Discovery.php` raccoglie i moduli candidati da **quattro
sorgenti**, ciascuna con una **priorità** (numero più alto = vince in caso di
slug duplicato):

| Sorgente | Dove cerca | Priorità |
|---|---|---|
| **bundled** | `<packageRoot>/modules/*` (moduli inclusi nel framework) | 10 |
| **vendor** | `<ROOT>/vendor/wonder-image/*` (scan filesystem) | 18 |
| **composer** | `vendor/composer/installed.php` e `installed.json` | (da metadati) |
| **local** | `<ROOT>/modules/*` (moduli in sviluppo nel sito) | 30 |

Per ogni cartella candidata, Discovery cerca un `module.json` e crea un
`Manifest`. La sorgente **composer** legge i metadati dei pacchetti installati
(con fallback tra `installed.php` e `installed.json`).

## Validazione (ManifestValidator)

`class/App/Module/ManifestValidator.php` valida ogni manifest. Vedi
[Manifest module.json](manifest.md) per i campi obbligatori e le regole reali.
Un manifest non valido fa scartare il modulo (con errori espliciti).

## Registro (Registry)

`class/App/Module/Registry.php` lavora sui moduli **abilitati**
(`Registry::enabled()` legge lo stato di abilitazione). Espone:

| Metodo | Cosa restituisce |
|---|---|
| `enabled()` | i manifest dei moduli abilitati |
| `modelDirectories()` | cartelle dei Model dei moduli |
| `resourceDirectories()` | cartelle delle Resource dei moduli |
| `routeFiles($area)` | file di route per area (`frontend`/`backend`/`api`) |
| `languagePaths()` | percorsi delle traduzioni |
| `mergePermissions($permits)` | unisce i permessi dei moduli al registro |

## Precedenza Model / Resource

I Model e le Resource dei moduli si inseriscono nella catena di scoperta tra il
core e il sito:

```
core framework  →  MODULI abilitati  →  sito (app/)  →  sito (custom/)
```

Una definizione del sito può sovrascrivere quella di un modulo; un modulo può
sovrascrivere il core. Dettagli in
[Architettura](../../introduzione/architettura.md).

## Permessi dei moduli

Nel registro permessi (`app/config/app/permission.php`) il merge dei moduli
avviene **dopo** il core e **prima** del custom del sito:

```php
Permissions::replace(\Wonder\App\Module\Registry::mergePermissions(Permissions::instance()));
```

Quindi: **core → moduli → custom**. Vedi [Builder permessi](../utenti/permessi.md).

## Route dei moduli

`Registry::routeFiles($area)` fornisce i file di route per area, caricati
insieme alle route di base del framework
(`app/config/routes/route.<area>.php`).

## Publish view dei moduli

`php forge publish:module <slug>` copia le view overrideabili dichiarate dal modulo in
`paths.views` verso il sito, sotto:

```text
custom/modules/<slug>/view/
```

Le view elencate in `views.sealed` nel `module.json` restano nel package: il
comando le salta con un avviso anche quando viene richiesto direttamente il
singolo file o si usa `--force`. Lo stesso elenco deve essere rispettato dal
resolver runtime del modulo.

Esempio:

```bash
php forge publish:module rsvp
```

Se il modulo `rsvp` dichiara `paths.views = "view"`, il comando copia, ad
esempio:

```text
vendor/wonder-image/rsvp/view/components/form.php
custom/modules/rsvp/view/components/form.php
```

Il comando non sovrascrive file già pubblicati. Usa `--force` per sovrascrivere,
`--dry-run` per vedere cosa verrebbe copiato, `--list` per elencare i file
publishabili, oppure un path relativo per pubblicare solo una parte:

```bash
php forge publish:module rsvp components/form.php
php forge publish:module rsvp --components
php forge publish:module rsvp --layouts
php forge publish:module rsvp --pages
```

Il publish è solo una copia nel sito. Perché l'override venga usato a runtime,
il modulo deve risolvere le proprie view passando prima dal path
`custom/modules/<slug>/view/...` e poi dal fallback del package.

## Home del backend

La home del backend (`app/view/pages/backend/home.php`) usa solo classi
Bootstrap, senza CSS proprio. Dall'alto:

- saluto e data, con il pulsante **Vai al sito**;
- la fascia di stato (`Wonder\Backend\Support\HomeStatus`): scheduler ed
  errori aperti nel core, più le voci dei moduli, descritte sotto;
- i riquadri dei moduli, descritti sotto;
- **Accesso rapido**: le sezioni della navigazione senza sottomenu;
- **Sezioni**: una card per sezione con i suoi link. Se la sezione ha gruppi,
  la card occupa tutta la riga con una colonna per gruppo.

Le sezioni arrivano da `Wonder\Backend\Support\HomeNavigation`, che applica
le autorità a ogni livello come la sidebar. Una nuova sezione o un nuovo gruppo
compaiono quindi da soli: non va toccata la home. Basta che il modulo dichiari
le sue Resource con `navigationSchema()`.

La home disegna tutto con tre Element del core, riusabili anche altrove:
`StatusCard` (stato), `LinkCard` (accesso rapido) e `LinkListCard` (sezioni).
Vedi [Componenti UI](../componenti/README.md#statuscard-linkcard-e-linklistcard).

### Voci di stato

Ogni voce della fascia di stato è una classe che implementa
`Wonder\Backend\Contracts\HomeStatusItem`:

| Metodo | Cosa restituisce |
|---|---|
| `status($authorities, $backendUrl)` | una `StatusCard`, o `null` per nasconderla |
| `order()` | ordine crescente tra le voci |

Le voci hanno una chiave. Il core registra `scheduler`
(`Wonder\Backend\Home\SchedulerStatus`) ed `errors`
(`Wonder\Backend\Home\ErrorStatus`). Un modulo aggiunge, sostituisce o toglie
voci nella propria configurazione:

```php
// config/module.php del modulo
'backend' => [
    'home_status' => [
        'orders' => \Wonder\Plugin\Shop\Backend\OrdersStatus::class, // nuova voce
        'errors' => false,                                              // toglie quella del core
    ],
],
```

Il sito può cambiare la configurazione del modulo in
`custom/config/modules/<slug>.php`. Per le voci del core, invece, usa
`HomeStatus::set('scheduler', MiaClasse::class)` o
`HomeStatus::set('scheduler', false)` nel proprio bootstrap. L'ordine di
precedenza è core, poi moduli, poi sito.

**Chi vede una voce lo decide la voce**, con i permessi della Resource a cui
porta il suo link, prima di leggere qualsiasi dato:

```php
public function status(array $authorities, string $backendUrl): ?StatusCard
{
    if (!OrderResource::allows($authorities, 'list')) {
        return null;
    }

    return StatusCard::make('Ordini', $this->pending())
        ->icon('bi-bag')
        ->href($backendUrl.'/'.OrderResource::path().'/');
}
```

`Resource::allows($authorities, $action)` applica la stessa regola delle route:
un'azione con autorità vuote è aperta a tutti, un'azione non dichiarata non è
mai permessa. Non scrivere i ruoli a mano. Le voci del core usano
`ScheduleResource` ed `ErrorReportResource` sull'azione `list`.

Una voce che solleva un'eccezione sparisce e finisce nel log: la home resta in
piedi.

### Riquadri dei moduli

Un modulo può aggiungere riquadri sotto lo stato dichiarandoli
nella propria configurazione:

```php
// config/module.php del modulo
'backend' => [
    'home_widgets' => [ \Wonder\Plugin\Gestionale\Backend\SetupWidget::class ],
],
```

Ogni classe implementa `Wonder\Backend\Contracts\HomeWidget`. `render()` può
restituire un Element già pronto, per esempio
`LinkCard::make(...)->render('bootstrap')` dentro una colonna `col-*`:

| Metodo | Cosa restituisce |
|---|---|
| `title()` | titolo del riquadro |
| `render()` | markup completo del riquadro |
| `authorities()` | ruoli che lo vedono; vuoto: tutti |
| `order()` | ordine crescente tra i riquadri |

`Wonder\Backend\Support\HomeWidgets::renderAll()` li raccoglie dai moduli
abilitati, filtra per ruolo dell'utente e li ordina. Una classe che non esiste o
un riquadro che solleva un'eccezione viene saltato e finisce nel log: la home
resta in piedi.

## Asset dei moduli (css/js/img)

Un modulo può spedire i propri asset dentro `paths.assets` (default
`resources/assets`). Nelle view si referenziano con l'helper globale
`module_asset()`:

```php
<link rel="stylesheet" href="<?= module_asset('immobili', 'css/immobili-map.css') ?>">
<script src="<?= module_asset('immobili', 'js/immobili-map.js') ?>"></script>
```

L'helper (che delega a `Wonder\App\Module\Assets::url()`) risolve l'URL in
questo ordine:

1. **copia pubblicata nel sito** — `assets/{ASSETS_VERSION}/<file>`: se esiste
   vince sempre, così il sito può personalizzare l'asset;
2. **file del modulo** — servito direttamente dal suo percorso reale sotto il
   root del sito (`vendor/wonder-image/<slug>/resources/assets/...` oppure
   `modules/<slug>/resources/assets/...`);
3. **fallback vendor** — se il root del modulo risolve fuori dal sito
   (repository Composer di tipo `path`), l'URL passa dal symlink
   `vendor/<package>/...`.

Se il file non esiste in nessuna posizione l'helper restituisce stringa vuota:
la view può quindi saltare il tag. Ogni URL include `?v=` per il cache busting
(versione del modulo, o `filemtime` per le copie pubblicate).

Per pubblicare gli asset nel sito (opzione, non obbligatoria — gli asset
funzionano anche serviti dal modulo):

```bash
php forge publish:module immobili --assets            # tutto paths.assets
php forge publish:module immobili --assets css        # solo una sottocartella
php forge publish:module immobili --assets --dry-run  # anteprima
php forge publish:module immobili --assets --force    # ri-pubblica dopo un update
```

La destinazione è `assets/{ASSETS_VERSION}/` (stessa struttura interna del
modulo, es. `css/immobili-map.css`). Attenzione: le copie pubblicate non si
aggiornano da sole quando il modulo viene aggiornato — vanno ri-pubblicate con
`--force`. Conviene prefissare i nomi file con lo slug del modulo
(`immobili-map.css`, non `map.css`) per evitare collisioni tra moduli.

## Errori comuni

- **Modulo non caricato** → non è abilitato in `custom/config/modules.php`, o il
  manifest non passa la validazione.
- **Slug duplicato** → vince la sorgente con priorità più alta (local > vendor >
  bundled); attenzione a moduli con lo stesso slug in più posti.
- **Permessi del modulo non visibili** → il merge avviene nel registro permessi:
  verifica che il modulo dichiari `permissions` nel manifest.
- **View pubblicata ma non usata** → verifica che il modulo risolva le view con
  fallback su `custom/modules/<slug>/view/...`.

## Checklist

- [ ] `module.json` valido (vedi [manifest](manifest.md))
- [ ] modulo abilitato in `custom/config/modules.php`
- [ ] `php forge status:modules` lo mostra abilitato
- [ ] `php forge validate:module <slug>` senza errori
- [ ] `php forge publish:module <slug> --list` mostra le view attese, se il modulo espone view
