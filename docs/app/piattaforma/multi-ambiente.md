---
icon: globe
---

# Multi-ambiente

Guida alla gestione di ambienti multipli (locale, staging, produzione) con Wonder Image.

## Problema

Il backend di Wonder Image e nato per operare direttamente in produzione: upload media, configurazione CSS, `.htaccess` e `robots.txt` venivano modificati live. Con ambienti multipli (locale + AI + staging + produzione) questa architettura crea divergenze: media mancanti in locale, CSS diversi tra ambienti, config server sovrascritta dal deploy.

Le soluzioni introdotte coprono tre aree: **media**, **sincronizzazione dati** e **config server**.

---

## Media: proxy fallback in locale

### Problema

I media uploadati in produzione (immagini, icone, documenti) non esistono in locale. Il DB clonato contiene riferimenti a file inesistenti -> immagini rotte.

### Soluzione

Il `WonderValetDriver` supporta un **proxy fallback**: se un file sotto `assets/upload/` non esiste localmente, Herd risponde con un redirect 302 verso la URL di produzione.

### Configurazione

Nel `.env` del progetto locale:

```dotenv
MEDIA_FALLBACK_URL=https://www.example.it
```

Il valore e la URL base del sito di produzione (senza slash finale). `forge start` genera il `.env` con la variabile gia predisposta (vuota) -- basta valorizzarla.

### Come funziona

1. Herd riceve una richiesta per `assets/upload/images/photo.jpg`
2. Il file non esiste localmente
3. `WonderValetDriver` legge `MEDIA_FALLBACK_URL` dal `.env` del progetto
4. Genera un redirect 302 verso `https://www.example.it/assets/upload/images/photo.jpg`
5. Il browser carica l'immagine dalla produzione

Il redirect usa un file temporaneo in `storage/tmp/_media_proxy.php` (ripulito automaticamente).

### Limiti

- Funziona solo con Herd/Valet (non con `php -S`)
- Richiede che la produzione sia raggiungibile dal browser locale
- Non sincronizza i file: li carica on-demand dalla produzione

---

## Sincronizzazione dati tra ambienti

### Problema

Molti dati di configurazione (CSS, SEO, dati aziendali, social, orari) sono salvati nel DB. Ogni ambiente ha il proprio DB -> i dati divergono silenziosamente.

### Soluzione

Il file `shared/sync-data.json` viene **committato in git** come single source of truth per i dati condivisi tra ambienti. Il workflow e:

1. L'utente modifica dati dal backend di produzione
2. `forge export` produce `shared/sync-data.json` dal DB
3. `shared/sync-data.json` viene committato in git
4. Al deploy, `forge update` importa `shared/sync-data.json` nel DB -> rigenera CSS
5. In locale, `forge import` dopo setup -> stessi dati della produzione

### Come funziona: `syncSchema()` sui Model

Il sistema scopre automaticamente quali tabelle sincronizzare: ogni Model che implementa `syncSchema()` viene incluso.

```php
// Model singleton (una sola riga, id=1)
public static function syncSchema(): ?SyncSchema
{
    return SyncSchema::singleton();
}

// Model multi-row
public static function syncSchema(): ?SyncSchema
{
    return SyncSchema::multiRow();
}

// Model non sincronizzabile (default)
public static function syncSchema(): ?SyncSchema
{
    return null;
}
```

I Model vengono scoperti via `ModelRegistry` -- framework, moduli e sito contribuiscono ciascuno le proprie tabelle sincronizzabili.

### Tabelle sincronizzabili nel framework

| Tabella | Tipo | Contenuto |
|---|---|---|
| `css_font` | Multi-row | Font families (Google Fonts, custom) |
| `css_color` | Multi-row | Palette colori con variabili CSS |
| `css_default` | Singleton | Tipografia, spacing, border-radius, button/badge |
| `css_input` | Singleton | Stili input e form |
| `css_modal` | Singleton | Stili modal |
| `css_dropdown` | Singleton | Stili dropdown |
| `css_alert` | Singleton | Stili alert/toast |
| `seo` | Singleton | Meta tag SEO default |
| `society` | Singleton | Dati aziendali |
| `society_address` | Singleton | Indirizzo sede operativa |
| `society_legal_address` | Singleton | Indirizzo sede legale |
| `society_social` | Singleton | Link social |
| `society_timetable` | Multi-row | Orari di apertura |

Le colonne di sistema (`id`, `last_modified`, `creation`, `deleted`) vengono escluse dall'export/import.

**Non sincronizzabili** (per design):
- `security` -- contiene API key, password, credenziali (specifiche per ambiente)
- `analytics` -- contiene GTM ID, Pixel ID (possono differire tra staging e produzione)
- `logos` -- contiene riferimenti a file media (i file non vengono sincronizzati)
- Tabelle utenti, log, consent -- dati runtime, non configurazione

### Aggiungere tabelle nel sito

Un sito puo aggiungere le proprie tabelle sincronizzabili definendo `syncSchema()` nei Model in `app/Models/` o `custom/class/Models/`:

```php
// app/Models/Config/MyConfig.php
namespace App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;

final class MyConfig extends Model
{
    public static string $table = 'my_config';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::singleton();
    }

    // ... tableSchema(), dataSchema()
}
```

La tabella apparira automaticamente in `forge export` / `forge import`.

### Comandi

#### `forge export [file]`

Esporta tutte le tabelle sincronizzabili in un file JSON:

```bash
php forge export                      # -> shared/sync-data.json (default)
php forge export custom-sync.json     # -> file custom
```

Output:

```
✅ Dati esportati in /path/to/shared/sync-data.json
   css_font: 2 righe
   css_color: 12 righe
   css_default: 1 riga
   seo: 1 riga
   society: 1 riga
   society_address: 1 riga
   ...
```

#### `forge import [file]`

Importa un file JSON nelle tabelle del DB. Se contiene tabelle CSS, rigenera anche `root.css` e `color.css`:

```bash
php forge import                      # <- shared/sync-data.json (default)
php forge import custom-sync.json     # <- file custom
php forge import --no-rebuild         # solo DB, senza rigenerare CSS
```

#### Import automatico in `forge update`

`build/update/css.php` chiama `TableSync::importIfExists($ROOT)` **prima** di rigenerare i CSS. Se `shared/sync-data.json` esiste nel root del progetto, viene importato automaticamente nel DB.

Questo significa che:

- il deploy con `forge update` allinea automaticamente il DB ai dati committati
- non serve chiamare `forge import` manualmente in CI

### Scegliere le tabelle da sincronizzare

Di default tutte le tabelle con `syncSchema()` vengono esportate/importate. Se un progetto ha bisogno di sincronizzare solo alcune tabelle, ci sono due modi per configurarlo.

**Via `.env`** (piu semplice):

```dotenv
SYNC_TABLES=css_font,css_color,css_default,seo,society
```

**Via PHP** (dal bootstrap del sito o da un file di configurazione custom):

```php
\Wonder\App\Support\TableSync::setSyncTables([
    'css_font',
    'css_color',
    'css_default',
    'seo',
    'society',
]);
```

Il setter ha precedenza sull'env. Passare `null` per tornare al default (tutte le tabelle).

I comandi CLI mostrano le tabelle escluse:

```
✅ Dati esportati in shared/sync-data.json
   css_font: 2 righe
   css_color: 10 righe
   css_default: 1 riga
   seo: 1 riga
   society: 1 riga
   ⊘ Escluse dal sync: css_input, css_modal, css_dropdown, css_alert, ...
```

### Classe `TableSync`

La logica centralizzata vive in `Wonder\App\Support\TableSync`:

- `discoverTables()` -- scopre tutte le tabelle con `syncSchema()` via ModelRegistry
- `syncTables()` -- restituisce le tabelle attive per il sync (configurabili)
- `setSyncTables(array $tables)` -- limita le tabelle da sincronizzare
- `exportConfig(?array $onlyTables)` -- esporta le tabelle attive (o un sottoinsieme)
- `importConfig(array $config, ?array $onlyTables)` -- importa le tabelle attive
- `importIfExists(string $root)` -- cerca `shared/sync-data.json` nel root e importa se presente
- `autoExport()` -- esporta automaticamente se `SYNC_AUTO_EXPORT=true`
- `resetCache()` -- resetta la cache di discovery (utile nei test)

Durante l'import, le tabelle vengono ordinate automaticamente in base alle
foreign key dichiarate in `Model::tableSchema()`: una tabella referenziata viene
popolata prima delle tabelle che la utilizzano. L'ordine configurato resta
stabile tra tabelle indipendenti e viene usato come fallback in caso di cicli.

### Classe `SyncSchema`

Value object che descrive il comportamento di sync di una tabella:

- `SyncSchema::singleton()` -- tabella con una sola riga (id=1)
- `SyncSchema::multiRow()` -- tabella con righe multiple
- `->exclude(['col1', 'col2'])` -- esclude colonne aggiuntive dall'export

### Auto-export dal backend

Quando il designer salva una qualunque configurazione CSS dal backend, il file `shared/sync-data.json` viene **rigenerato automaticamente** nel root del progetto, se la variabile d'ambiente `SYNC_AUTO_EXPORT` e attiva.

#### Configurazione

Nel `.env` di produzione:

```dotenv
SYNC_AUTO_EXPORT=true
```

In locale il valore di default e `false` -- l'export automatico non serve perche il file viene importato da git, non generato localmente.

#### Come funziona

1. Il designer modifica un colore/font/stile dal backend e salva
2. La Resource CSS rigenera `root.css` / `color.css` come prima
3. `TableSync::autoExport()` viene chiamato subito dopo
4. Se `SYNC_AUTO_EXPORT=true`, esporta **tutte** le tabelle sincronizzabili in `shared/sync-data.json`
5. Scrittura solo se il contenuto e effettivamente cambiato (evita diff git spuri)
6. Se qualcosa fallisce, l'errore viene ignorato silenziosamente

#### Hook coinvolti

| Resource | Hook | Cosa rigenera |
|---|---|---|
| `CssDefaultResource` | `afterUpdate` | `cssRoot()` + auto-export |
| `CssInputResource` | `afterUpdate` | `cssRoot()` + auto-export |
| `CssModalResource` | `afterUpdate` | `cssRoot()` + auto-export |
| `CssDropdownResource` | `afterUpdate` | `cssRoot()` + auto-export |
| `CssAlertResource` | `afterUpdate` | `cssRoot()` + auto-export |
| `CssColorResource` | `afterStore`, `afterUpdate` | `cssRoot()` + `cssColor()` + auto-export |
| `CssFontResource` | `afterStore`, `afterUpdate`, `afterDelete` | `cssRoot()` + auto-export |

### Workflow consigliato

**Prima configurazione locale:**

```bash
git pull                    # contiene shared/sync-data.json
php forge update --local    # importa sync-data.json + rigenera CSS
```

**Dopo modifica in produzione (con auto-export attivo):**

```bash
# shared/sync-data.json viene aggiornato automaticamente ad ogni save CSS.
# Per committare le modifiche:
git add shared/sync-data.json
git commit -m "Update sync data from production"
git push
```

**Dopo modifica in produzione (senza auto-export):**

```bash
# In produzione (o via SSH):
php forge export

# Commit e push:
git add shared/sync-data.json
git commit -m "Update sync data from production"
git push
```

**In locale dopo il pull:**

```bash
git pull
php forge import    # o php forge update --local
```

---

## Config server: `.htaccess` e `robots.txt` parametrici

### `.htaccess`

#### Stato precedente

- `.htaccess` era tracciato in git -> il deploy FTP sovrascriveva il file di produzione
- Due template divergenti: uno in `Build.php`, uno in `configuration_file.php`
- Force-www inconsistente tra i due

#### Stato attuale

- `.htaccess` e **gitignored** e generato da `forge build` / `forge update`
- Un solo template: `Build::htaccessTemplate(bool $forceWww)` (single source of truth)
- Force-www parametrico tramite `APP_FORCE_WWW` nel `.env` o `--force-www` in CLI

**Generazione:**

| Contesto | Comando | Force-www |
|---|---|---|
| CI (GitHub Actions) | `forge build --force-www` | Da `vars.FORCE_WWW` |
| Locale | `forge update --local` | Da `$_ENV['APP_FORCE_WWW']` |
| Server (update HTTP) | `forge update` | Da `$_ENV['APP_FORCE_WWW']` |

**Aggiornamento file esistenti:**

Se `.htaccess` esiste gia, `build/update/configuration_file.php` aggiorna solo il blocco `# WONDER ROUTER START ... # WONDER ROUTER END`, preservando personalizzazioni dell'utente.

### `robots.txt`

Creato solo se manca. Il template e parametrico:

- **Dominio**: letto da `$PAGE->domain` (runtime) o `$_ENV['APP_DOMAIN']` (fallback)
- **Prefisso www**: determinato da `APP_FORCE_WWW`

```
User-agent: *
Disallow: /backend/

Sitemap: https://www.example.it/shared/sitemap/sitemap.xml
```

Il `www.` viene aggiunto solo se `APP_FORCE_WWW=true`.

---

## Variabili d'ambiente

Variabili introdotte per il supporto multi-ambiente:

| Variabile | Dove | Default | Descrizione |
|---|---|---|---|
| `MEDIA_FALLBACK_URL` | `.env` locale | *(vuoto)* | URL base produzione per proxy media in Herd |
| `APP_FORCE_WWW` | `.env` / Bitwarden | `false` | Abilita redirect www in `.htaccess` e `robots.txt` |
| `APP_DOMAIN` | `.env` / `vars.APP_DOMAIN` | -- | Dominio del sito (fallback per `robots.txt`) |
| `SYNC_AUTO_EXPORT` | `.env` produzione | `false` | Se `true`, rigenera `shared/sync-data.json` ad ogni save dal backend |
| `SYNC_TABLES` | `.env` | *(tutte)* | Comma-separated: tabelle da includere nel sync (es. `css_font,css_color,seo`) |

### GitHub Actions variables

| Variable | Descrizione |
|---|---|
| `FORCE_WWW` | Se `true`, `forge build` genera `.htaccess` con force-www |
| `APP_DOMAIN` | Dominio di produzione |
| `ASSETS_VERSION` | Versione asset |

---

## Riepilogo: cosa e generato, cosa e committato

| File | In git? | Generato da | Source of truth |
|---|---|---|---|
| `shared/sync-data.json` | **Si** | `forge export` | Git (committato dal designer/dev) |
| `root.css`, `color.css` | No | `forge update` (da DB <- `sync-data.json`) | DB `css_*` |
| `.htaccess` | No | `forge build` / `forge update` | Template `Build::htaccessTemplate()` + `.env` |
| `robots.txt` | No | `forge update` (se manca) | Template parametrico + `.env` |
| `handler/index.php` | No | `forge build` / `forge update --local` | Template framework |
| `assets/upload/**` | No | Upload backend | Produzione filesystem |
| `.env` | No | `forge start` / `forge config` / CI workflow | Specifico per ambiente |
