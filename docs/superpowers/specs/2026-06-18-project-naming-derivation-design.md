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

## Un'unica identità, tre separatori (+ il dominio)

Il **nome progetto** è quello della cartella `<name>-<tld>` riformattato per
target: cambia solo il **separatore dell'ultimo segmento (il TLD)**. Il TLD
**resta sempre** parte del nome (non viene strippato): è solo reso `-`, `_` o `.`.

| Cartella          | `APP_DOMAIN` (`.`) | `APP_URL` prod              | `APP_URL` locale (Herd)      | composer `name` (kebab `-`)   | `DB_DATABASE` (snake `_`) | `DB_USERNAME`           |
|-------------------|--------------------|-----------------------------|------------------------------|-------------------------------|---------------------------|-------------------------|
| `wonderimage-it`  | `wonderimage.it`   | `https://wonderimage.it`    | `https://wonderimage.test`   | `wonder-image/wonderimage-it` | `main:wonderimage_it`     | `wonderimage_it_user`   |
| `progetto-site`   | `progetto.site`    | `https://progetto.site`     | `https://progetto.test`      | `wonder-image/progetto-site`  | `main:progetto_site`      | `progetto_site_user`    |
| `acme-shop-com`   | `acme-shop.com`    | `https://acme-shop.com`     | `https://acme-shop.test`     | `wonder-image/acme-shop-com`  | `main:acme_shop_com`      | `acme_shop_com_user`    |

### Regole di formato
- **`APP_DOMAIN`**: dominio **con estensione** (`name.tld`, separatore `.`).
  `.site` è un TLD valido a tutti gli effetti.
- **project name** (composer): **kebab** (separatore `-`), **TLD incluso**:
  `wonderimage-it`. È lo stesso identificatore del dominio, solo col `-`.
- **DB** (`DB_DATABASE` main, `DB_USERNAME`): **snake** (separatore `_`), **TLD
  incluso**: `wonderimage_it`; username `<snake>_user` → `wonderimage_it_user`.
- **Host Herd locale**: il TLD reale viene sostituito con `.test` →
  `wonderimage.test` (qui, e solo qui, l'ultimo segmento è rimpiazzato).
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
  `name.tld` usando `defaultProjectLabel()` (per `name`, TLD strippato) +
  `domainTld()` (per il TLD). Se non c'è TLD riconosciuto ritorna `''` (→ il
  chiamante chiede il dominio).
- Nuovo helper `projectName(cwd, fallbackDomain)`: kebab della **cartella con
  TLD incluso** (`normalizeProjectSlug`, non `defaultProjectLabel`) →
  `wonderimage-it`. Fallback sul kebab del dominio. Alimenta il nome composer.
- `buildAppUrl()` / `buildHerdHost()` restano invariati: usano `defaultProjectLabel()`
  (TLD strippato) per l'host Herd e funzionano una volta che `APP_DOMAIN`
  contiene l'estensione.

### 2. `class/Console/Commands/LocalEnvironmentCommand.php`
- `deriveDatabaseNameFromAppDomain()`: snake-case via `normalizeProjectSlug()`
  **mantenendo il TLD**, così `wonderimage.it` / `wonderimage-it` →
  `wonderimage_it`. `buildDefaultDbUsername()` produce `wonderimage_it_user`.

### 3. `class/Console/Commands/DbInit.php`
- Scrivere `APP_DOMAIN` come **dominio completo con estensione** (coerente con
  `config`), non come slug.
- Derivare nome DB e username dal **project name della cartella** (kebab con TLD),
  così `db:init` resta allineato a `config`.

### 4. `class/Console/Commands/LocalStart.php` (impatto indiretto)
- Condivide `defaultAppDomain()`: ora che ritorna il dominio completo, il
  confronto per il messaggio "APP_DOMAIN sincronizzato" va reso like-for-like
  (dominio vs dominio, via `normalizeDomain()`) per non scattare a ogni
  `forge start`. La semantica di overwrite-from-folder di `forge start` resta
  invariata: scrive comunque il dominio completo corretto.

## Test

Coprire con unit test deterministici i casi della tabella (più gli edge):

- `wonderimage-it` → domain `wonderimage.it`, composer `wonder-image/wonderimage-it`,
  DB `wonderimage_it` / `wonderimage_it_user`.
- `progetto-site` → domain `progetto.site` (`.site` come TLD), composer
  `wonder-image/progetto-site`, DB `progetto_site`.
- `acme-shop-com` → DB snake `acme_shop_com`, username `acme_shop_com_user`.
- `wonderimage` (no TLD) → `defaultAppDomain` ritorna `''`; project name
  `wonderimage`.
- `acme-foo` (segmento finale non-TLD) → project name `acme-foo`, dominio chiesto.
- `.env` con `APP_DOMAIN=altro.com` già presente → `APP_DOMAIN` non sovrascritto;
  composer/DB comunque dalla cartella.

## Fuori scope

- Rinomina delle chiavi env (`DB_DATABASE`→`DB_NAME`, ecc.).
- Modifiche a `EnvCompat`, `Credentials`, flusso Bitwarden/provision.
