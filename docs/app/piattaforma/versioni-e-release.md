# Versioni e release

## Da dove arriva la versione

La versione del framework si scrive in **un solo posto**: il campo `"version"`
di `composer.json`. Non va aggiornata a mano: la aggiorna `composer release`.

Al bootstrap `wonder-image.php` chiama `Wonder\App\Version::get()`, e il
valore finisce in `$APP_VERSION` e nella costante `APP_VERSION`. Li usano il
footer del backend, i log di `UpdateRunner` e le chiamate `/auth/update/`.

`Version::get()` cerca la versione in quest'ordine:

1. il `"version"` di `vendor/wonder-image/app/composer.json`, che corrisponde
   sempre al codice installato, anche con `dev-main`;
2. i metadati di Composer (`Composer\InstalledVersions`), per i deploy che
   rimuovono `composer.json` dai pacchetti installati;
3. `dev`.

`Version::label()` aggiunge ramo e commit quando il pacchetto è installato da
branch, per esempio `2.3.0 (dev-main@60c6b7d)`. Il footer del backend mostra
questa etichetta, così si vede subito quale commit gira su un sito.
`Version::reference()` restituisce solo l'hash corto.

## Pubblicare una release

Dalla root del pacchetto, su `main` pulito e allineato a `origin/main`:

```bash
composer release -- 2.4.0
```

```bash
composer release -- 2.4.0-beta.1
```

```bash
composer release -- patch
```

| Argomento | Risultato |
| --- | --- |
| `X.Y.Z` | release stabile, marcata **Latest** su GitHub |
| `X.Y.Z-alpha.N` / `-beta.N` / `-rc.N` | **pre-release** su GitHub, non Latest |
| `patch` / `minor` / `major` | calcola la versione successiva; `patch` su una pre-release la promuove (`2.4.0-beta.2` → `2.4.0`) |

| Opzione | Effetto |
| --- | --- |
| `--dry-run` | mostra cosa farebbe, senza modificare nulla |
| `--yes` | salta la conferma |
| `--no-github` | crea e pubblica solo il tag, senza GitHub release |

Il comando:

1. verifica branch `main`, working tree pulito, allineamento con `origin/main`,
   tag non ancora esistente, versione maggiore di quella attuale e `gh`
   autenticato;
2. aggiorna `"version"` in `composer.json`, crea il commit `Release X.Y.Z` e il
   tag annotato `vX.Y.Z`;
3. fa il push di `main` e del tag;
4. crea la GitHub release con note generate automaticamente
   (`gh release create --generate-notes`).

Lo script è in `bin/release.php` ed è registrato come script Composer
`release`, come `npm run release` in `wonder-image/lib`.

## Formato dei tag

I tag devono essere `vX.Y.Z` o `vX.Y.Z-beta.N`. Il formato `v.X.Y.Z`, usato per
qualche release della serie 2.2, non è una versione valida per Composer:
Packagist lo ignora. Inoltre il tag deve coincidere con il `"version"` di
`composer.json` in quel commit, altrimenti Packagist lo scarta. Il comando di
release garantisce entrambe le condizioni.

## Nei siti

Con `dev-main` basta `composer update`, e il footer mostra versione e commit.
Per fissare un sito a una release stabile:

```bash
composer require wonder-image/app:^2.4
```

Per una pre-release serve la versione esatta (`wonder-image/app:2.4.0-beta.1`)
oppure una `minimum-stability` adeguata nel sito.
