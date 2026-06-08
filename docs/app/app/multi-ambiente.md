---
icon: globe
---

# Multi-ambiente

Guida alla gestione di ambienti multipli (locale, staging, produzione) con Wonder Image.

## Problema

Il backend di Wonder Image è nato per operare direttamente in produzione: upload media, configurazione CSS, `.htaccess` e `robots.txt` venivano modificati live. Con ambienti multipli (locale + AI + staging + produzione) questa architettura crea divergenze: media mancanti in locale, CSS diversi tra ambienti, config server sovrascritta dal deploy.

Le soluzioni introdotte coprono tre aree: **media**, **configurazione CSS** e **config server**.

---

## Media: proxy fallback in locale

### Problema

I media uploadati in produzione (immagini, icone, documenti) non esistono in locale. Il DB clonato contiene riferimenti a file inesistenti → immagini rotte.

### Soluzione

Il `WonderValetDriver` supporta un **proxy fallback**: se un file sotto `assets/upload/` non esiste localmente, Herd risponde con un redirect 302 verso la URL di produzione.

### Configurazione

Nel `.env` del progetto locale:

```dotenv
MEDIA_FALLBACK_URL=https://www.example.it
```

Il valore è la URL base del sito di produzione (senza slash finale). `forge start` genera il `.env` con la variabile già predisposta (vuota) — basta valorizzarla.

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

## CSS: configurazione grafica versionata in git

### Problema

La configurazione grafica (colori, font, tipografia, input, modal, dropdown, alert) è salvata in 7 tabelle DB. Ogni ambiente ha il proprio DB → la grafica diverge silenziosamente.

### Soluzione

Il file `css-config.json` viene **committato in git** come single source of truth per i design token. Il workflow è:

1. Il designer modifica colori/font dal backend di produzione → CSS rigenerati live
2. `forge css:export` produce `css-config.json` dal DB
3. `css-config.json` viene committato in git
4. Al deploy, `forge update` importa `css-config.json` nel DB → rigenera CSS
5. In locale, `forge css:import` dopo setup → stessa grafica della produzione

### Comandi

#### `forge css:export [file]`

Esporta le 7 tabelle CSS in un file JSON:

```bash
php forge css:export                    # → css-config.json (default)
php forge css:export design-tokens.json # → file custom
```

Output:

```
✅ Configurazione CSS esportata in /path/to/css-config.json
   css_font: 2 righe
   css_color: 12 righe
   css_default: 1 riga
   css_input: 1 riga
   css_modal: 1 riga
   css_dropdown: 1 riga
   css_alert: 1 riga
```

#### `forge css:import [file]`

Importa un file JSON nelle 7 tabelle CSS e rigenera `root.css` + `color.css`:

```bash
php forge css:import                     # ← css-config.json (default)
php forge css:import design-tokens.json  # ← file custom
php forge css:import --no-rebuild        # solo DB, senza rigenerare CSS
```

#### Import automatico in `forge update`

`build/update/css.php` chiama `CssConfigSync::importIfExists($ROOT)` **prima** di rigenerare i CSS. Se `css-config.json` esiste nel root del progetto, viene importato automaticamente nel DB.

Questo significa che:

- il deploy con `forge update` allinea automaticamente il DB ai design token committati
- non serve chiamare `forge css:import` manualmente in CI

### Tabelle coinvolte

| Tabella | Tipo | Contenuto |
|---|---|---|
| `css_font` | Multi-row | Font families (Google Fonts, custom) |
| `css_color` | Multi-row | Palette colori con variabili CSS |
| `css_default` | Singleton | Tipografia, spacing, border-radius, button/badge |
| `css_input` | Singleton | Stili input e form |
| `css_modal` | Singleton | Stili modal |
| `css_dropdown` | Singleton | Stili dropdown |
| `css_alert` | Singleton | Stili alert/toast |

Le colonne di sistema (`id`, `last_modified`, `creation`, `deleted`) vengono escluse dall'export/import.

### Classe `CssConfigSync`

La logica centralizzata vive in `Wonder\App\Support\CssConfigSync`:

- `exportConfig()` — legge tutte e 7 le tabelle, restituisce array
- `importConfig(array $config)` — singleton: update id=1; multi-row: truncate + re-insert
- `importIfExists(string $root)` — cerca `css-config.json` nel root e importa se presente
- `autoExport()` — esporta `css-config.json` automaticamente se `CSS_AUTO_EXPORT=true` (vedi sotto)

Usata da `forge css:export`, `forge css:import`, `build/update/css.php` e dagli hook delle Resource CSS.

### Auto-export dal backend

Quando il designer salva una qualunque configurazione CSS dal backend (colori, font, tipografia, input, modal, dropdown, alert), il file `css-config.json` viene **rigenerato automaticamente** nel root del progetto, se la variabile d'ambiente `CSS_AUTO_EXPORT` è attiva.

#### Configurazione

Nel `.env` di produzione:

```dotenv
CSS_AUTO_EXPORT=true
```

In locale il valore di default è `false` — l'export automatico non serve perché il file viene importato da git, non generato localmente.

#### Come funziona

1. Il designer modifica un colore/font/stile dal backend e salva
2. La Resource CSS rigenera `root.css` / `color.css` come prima
3. `CssConfigSync::autoExport()` viene chiamato subito dopo
4. Se `CSS_AUTO_EXPORT=true`, il metodo esporta le 7 tabelle in `css-config.json`
5. Scrittura solo se il contenuto è effettivamente cambiato (evita diff git spuri)
6. Se qualcosa fallisce, l'errore viene ignorato silenziosamente — il salvataggio CSS non deve mai fallire per colpa dell'export

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
git pull                    # contiene css-config.json
php forge update --local    # importa css-config.json + rigenera CSS
```

**Dopo modifica grafica in produzione (con auto-export attivo):**

```bash
# css-config.json viene aggiornato automaticamente ad ogni save dal backend.
# Per committare le modifiche:
git add css-config.json
git commit -m "Update CSS config from production"
git push
```

**Dopo modifica grafica in produzione (senza auto-export):**

```bash
# In produzione (o via SSH):
php forge css:export

# Commit e push:
git add css-config.json
git commit -m "Update CSS config from production"
git push
```

**In locale dopo il pull:**

```bash
git pull
php forge css:import    # o php forge update --local
```

---

## Config server: `.htaccess` e `robots.txt` parametrici

### `.htaccess`

#### Stato precedente

- `.htaccess` era tracciato in git → il deploy FTP sovrascriveva il file di produzione
- Due template divergenti: uno in `Build.php`, uno in `configuration_file.php`
- Force-www inconsistente tra i due

#### Stato attuale

- `.htaccess` è **gitignored** e generato da `forge build` / `forge update`
- Un solo template: `Build::htaccessTemplate(bool $forceWww)` (single source of truth)
- Force-www parametrico tramite `APP_FORCE_WWW` nel `.env` o `--force-www` in CLI

**Generazione:**

| Contesto | Comando | Force-www |
|---|---|---|
| CI (GitHub Actions) | `forge build --force-www` | Da `vars.FORCE_WWW` |
| Locale | `forge update --local` | Da `$_ENV['APP_FORCE_WWW']` |
| Server (update HTTP) | `forge update` | Da `$_ENV['APP_FORCE_WWW']` |

**Aggiornamento file esistenti:**

Se `.htaccess` esiste già, `build/update/configuration_file.php` aggiorna solo il blocco `# WONDER ROUTER START ... # WONDER ROUTER END`, preservando personalizzazioni dell'utente.

### `robots.txt`

Creato solo se manca. Il template è parametrico:

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
| `APP_DOMAIN` | `.env` / `vars.APP_DOMAIN` | — | Dominio del sito (fallback per `robots.txt`) |
| `CSS_AUTO_EXPORT` | `.env` produzione | `false` | Se `true`, rigenera `css-config.json` ad ogni save CSS dal backend |

### GitHub Actions variables

| Variable | Descrizione |
|---|---|
| `FORCE_WWW` | Se `true`, `forge build` genera `.htaccess` con force-www |
| `APP_DOMAIN` | Dominio di produzione |
| `ASSETS_VERSION` | Versione asset |

---

## Riepilogo: cosa è generato, cosa è committato

| File | In git? | Generato da | Source of truth |
|---|---|---|---|
| `css-config.json` | **Sì** | `forge css:export` | Git (committato dal designer/dev) |
| `root.css`, `color.css` | No | `forge update` (da DB ← `css-config.json`) | DB `css_*` |
| `.htaccess` | No | `forge build` / `forge update` | Template `Build::htaccessTemplate()` + `.env` |
| `robots.txt` | No | `forge update` (se manca) | Template parametrico + `.env` |
| `handler/index.php` | No | `forge build` / `forge update --local` | Template framework |
| `assets/upload/**` | No | Upload backend | Produzione filesystem |
| `.env` | No | `forge start` / `forge config` / CI workflow | Specifico per ambiente |
