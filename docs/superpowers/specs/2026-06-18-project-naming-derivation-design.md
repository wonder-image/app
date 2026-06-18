# `php forge config` — derivazione coerente dei nomi di progetto

## Problema

Oggi `php forge config` (e di riflesso `php forge db:init`) collassa in un solo
slug tre identità diverse del progetto. La causa è `Config::defaultProjectLabel()`:
striscia il TLD dal nome cartella e quel valore *già strippato* finisce in
`APP_DOMAIN`. Risultato: il dominio perde l'estensione e dominio, nome composer e
nome DB diventano lo stesso valore, generando confusione su `APP_DOMAIN`,
`APP_URL`, `DB_DATABASE`, `DB_USERNAME` e `name` di `composer.json`.

## Convenzione di partenza

La **cartella di progetto è la fonte di verità** ed è nominata `<name>-<tld>`:
l'**ultimo segmento separato da `-` è il TLD**.

Esempi: `wonderimage-it`, `spingy-it`, `progetto-site`, `acme-shop-com`.

## Due identità distinte (oggi erroneamente unificate)

| Cartella          | `APP_DOMAIN`      | `APP_URL` prod              | `APP_URL` locale (Herd)      | composer `name`           | `DB_DATABASE`        | `DB_USERNAME`       |
|-------------------|-------------------|-----------------------------|------------------------------|---------------------------|----------------------|---------------------|
| `wonderimage-it`  | `wonderimage.it`  | `https://wonderimage.it`    | `https://wonderimage.test`   | `wonder-image/wonderimage`| `main:wonderimage`   | `wonderimage_user`  |
| `progetto-site`   | `progetto.site`   | `https://progetto.site`     | `https://progetto.test`      | `wonder-image/progetto`   | `main:progetto`      | `progetto_user`     |
| `acme-shop-com`   | `acme-shop.com`   | `https://acme-shop.com`     | `https://acme-shop.test`     | `wonder-image/acme-shop`  | `main:acme_shop`     | `acme_shop_user`    |

### Regole di formato
- **`APP_DOMAIN`**: dominio **con estensione** (`name.tld`). `.site` è un TLD
  valido a tutti gli effetti per il dominio.
- **project name** (composer): **kebab**, TLD **strippato**. È il punto in cui
  l'istruzione "non considerare `.site`" si applica: `.site`/`-site` (come ogni
  TLD) non fa parte del nome progetto.
- **DB** (`DB_DATABASE` main, `DB_USERNAME`): **snake** del project name; lo
  username è `<snake>_user`.
- Le **chiavi** env NON cambiano (`DB_DATABASE`/`DB_USERNAME` restano tali);
  cambia solo il **valore derivato**. `EnvCompat`/`Credentials` (alias
  `DB_USER`/`DB_NAME`) non vengono toccati.

## Regole di precedenza e fallback

1. **Project name** (composer + DB) deriva **sempre dal nome cartella**, anche se
   `APP_DOMAIN` nel `.env` è diverso.
2. **`APP_DOMAIN`**: se già valorizzato nel `.env`, **vince il `.env`** (non si
   sovrascrive). Si ricostruisce dalla cartella solo se assente/vuoto.
3. **Cartella senza TLD riconoscibile** (es. `wonderimage`, o ultimo segmento non
   in whitelist come `acme-foo`): non si inventa un'estensione → si **chiede il
   dominio all'utente** (`askRequiredDomain`). L'ultimo segmento viene trattato
   come TLD **solo** se è nella whitelist `PROJECT_LABEL_TLDS` (quindi `acme-foo`
   resta progetto `acme-foo`).

## Modifiche (3 file)

### 1. `class/Console/Commands/Config.php`
- Aggiungere `'site'` a `PROJECT_LABEL_TLDS`.
- Nuovo helper `domainTld(string $value): string` — ritorna il TLD (`it`, `site`,
  `com`, …) se l'ultimo segmento (`-` o `.`) è in whitelist, altrimenti `''`.
- Riscrivere `defaultAppDomain(cwd)`: ricostruisce il **dominio completo**
  `name.tld` usando `defaultProjectLabel()` (per `name`) + `domainTld()` (per il
  TLD). Se non c'è TLD riconosciuto ritorna `''` (→ il chiamante chiede il dominio).
- Il nome composer deriva dal **project label della cartella** (kebab), non da
  `APP_DOMAIN`: evita che `wonderimage.it` rientri come `wonderimage-it`.
  `composerProjectName()` continua a normalizzare ma riceve la label già
  TLD-strippata.
- `buildAppUrl()` / `buildHerdHost()` restano invariati: funzionano già
  correttamente una volta che `APP_DOMAIN` contiene l'estensione.

### 2. `class/Console/Commands/LocalEnvironmentCommand.php`
- `deriveDatabaseNameFromAppDomain()`: strippare prima il TLD via
  `defaultProjectLabel()` e poi fare snake-case, così `wonderimage.it` →
  `wonderimage` (non `wonderimage_it`). `buildDefaultDbUsername()` produce di
  conseguenza `wonderimage_user`.

### 3. `class/Console/Commands/DbInit.php`
- Scrivere `APP_DOMAIN` come **dominio completo con estensione** (coerente con
  `config`), non come slug.
- Derivare nome DB e username dal **project label della cartella**, così
  `db:init` resta allineato a `config`.

### 4. `class/Console/Commands/LocalStart.php` (impatto indiretto)
- Condivide `defaultAppDomain()`: ora che ritorna il dominio completo, il
  confronto per il messaggio "APP_DOMAIN sincronizzato" va reso like-for-like
  (dominio vs dominio, via `normalizeDomain()`) per non scattare a ogni
  `forge start`. La semantica di overwrite-from-folder di `forge start` resta
  invariata: scrive comunque il dominio completo corretto.

## Test

Coprire con unit test deterministici i casi della tabella (più gli edge):

- `wonderimage-it` → domain `wonderimage.it`, composer `wonder-image/wonderimage`,
  DB `wonderimage` / `wonderimage_user`.
- `progetto-site` → domain `progetto.site` (`.site` tenuto nel dominio, strippato
  dal nome), composer `wonder-image/progetto`.
- `acme-shop-com` → DB snake `acme_shop`, username `acme_shop_user`.
- `wonderimage` (no TLD) → `defaultAppDomain` ritorna `''`; project name
  `wonderimage`.
- `acme-foo` (segmento finale non-TLD) → project name `acme-foo`, dominio chiesto.
- `.env` con `APP_DOMAIN=altro.com` già presente → `APP_DOMAIN` non sovrascritto;
  composer/DB comunque dalla cartella.

## Fuori scope

- Rinomina delle chiavi env (`DB_DATABASE`→`DB_NAME`, ecc.).
- Modifiche a `EnvCompat`, `Credentials`, flusso Bitwarden/provision.
