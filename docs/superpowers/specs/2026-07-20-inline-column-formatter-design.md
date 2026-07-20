# Inline column formatter (Closure) — design

**Data:** 2026-07-20
**Repo primario:** `wonder-image/app` (framework). Primo consumatore: modulo `wonder-image/immobili`.
**Stato:** proposta di design approvata a voce; in attesa di review della spec scritta.
**Precedente:** [2026-07-17-table-column-formatter-design.md](2026-07-17-table-column-formatter-design.md) (formatter con nome). Questa spec ne estende l'API.

## Motivazione

`TableColumn::formatter('nome')` oggi accetta **solo una stringa**: la logica del
formatter va registrata altrove (`config/formatters.php` + `boot.files`) e
referenziata per nome. Si vuole poter scrivere il formatter **inline** nel
`tableSchema()`:

```php
TableColumn::key('prezzo')->formatter(fn (array $row): string => immobiliListPrice($row)),
```

mantenendo funzionante la forma per nome (retro-compatibilità).

## Vincolo tecnico invariato

Il formato delle colonne fa il giro **client → server**: `list-table.php` →
`ListProvider::buildColumns($_POST['fields'])`. Sul filo può viaggiare **solo una
stringa**; una closure non è serializzabile. Quindi una closure inline va
**risolta lato server** — mai inviata al client.

## Design del framework (`wonder-image/app`)

Ricalca il pattern già esistente `ColumnFunctionRegistry::fromResources()`, che
scandisce i `tableSchema()` delle resource registrate (lazy, per-request).

### 1. `Column::formatter(string|Closure $formatter): self`
`class/Elements/Table/Column.php` — allargare la firma da `string` a
`string|Closure`. Salva il valore in `schema['formatter']` così com'è (stringa =
nome; closure = closure).

### 2. `ColumnFormatterRegistry::fromResources()` (nuovo)
`class/Backend/Table/ColumnFormatterRegistry.php` — metodo **lazy per-request**
(flag `$scanned` come il `$resolved` di `ColumnFunctionRegistry`):

- itera `ResourceRegistry::classes()`; per ognuna chiama `tableSchema()`;
- per ogni colonna il cui `formatter` (da `toArray()`) è una `Closure`, registra
  la closure sotto la **chiave derivata** `{slug}.{colonna}`
  (`$resourceClass::slug().'.'.$columnName`);
- `try/catch \Throwable` attorno all'iterazione (come `fromResources()` esistente).

`has()` e `call()` chiamano `fromResources()` per primo (se non ancora scanned),
così le chiavi derivate si risolvono **sia al render iniziale sia in AJAX**.

### 3. Emissione della chiave in `ResourceTableRenderer::legacyColumnFormat()`
`class/Backend/Support/ResourceTableRenderer.php` — dove oggi passa
`$config['formatter']` (stringa) a `$format['formatter']`:

```php
if (isset($config['formatter'])) {
    if ($config['formatter'] instanceof \Closure) {
        // chiave derivata: sul filo va la stringa, mai la closure
        $format['formatter'] = $this->slug.'.'.((string) ($config['name'] ?? ''));
    } elseif (is_string($config['formatter']) && trim($config['formatter']) !== '') {
        $format['formatter'] = trim($config['formatter']);
    }
}
```

`$this->slug` è disponibile (`private string $slug`); `$config` proviene da
`$column->toArray()` e contiene `name` e `formatter`.

### 4. Dispatch in `Field` — invariato
`Field::setValue()` già instrada `$format['formatter']` (stringa) →
`ColumnFormatterRegistry::has()/call()`. Con `fromResources()` lazy, la chiave
derivata risolve la closure registrata. Nessuna modifica qui.

### Chiavi e collisioni
La chiave derivata `{slug}.{colonna}` è unica per resource+colonna (una resource
non ha due colonne omonime). Coincide per costruzione con la convenzione dei nomi
namespaced già in uso (es. `immobili.prezzo`), quindi la migrazione da nome a
inline **non cambia la chiave sul filo**. La registrazione esplicita per nome
(`boot.files`) e la chiave derivata condividono lo spazio dei nomi; se entrambe
registrano la stessa chiave, l'ultima vince — nell'uso reale non coesistono
(o inline o boot.files per la stessa colonna).

### Compatibilità
`->formatter('nome')` + `boot.files` continua a funzionare identico. Il ramo
stringa in `legacyColumnFormat` è invariato.

## Uso lato modulo `immobili` (parte della stessa consegna)

Riscrivere `ImmobileResource::tableSchema()` con formatter **inline** e spostare
l'**immagine in 2ª colonna**:

```php
TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
TableColumn::key('image')->formatter(static fn (array $row): string => immobiliListImage($row))->label('')->size('little'),
TableColumn::key('nome')->formatter(static fn (array $row): string => immobiliListNome($row)),
TableColumn::key('comune_nome')->text()->size('medium'),
TableColumn::key('prezzo')->formatter(static fn (array $row): string => immobiliListPrice($row)),
TableColumn::key('superficie')->formatter(static fn (array $row): string => immobiliFormatSurface($row['superficie'] ?? 0)),
TableColumn::key('creation')->date()->sortable(),
TableColumn::key('sold')->booleanBadge('sold')->badgeOff('In vendita','bi bi-tag','primary')->badgeOn('Venduto','bi bi-check2-circle','dark')->size('little'),
TableColumn::key('visible')->visibleBadge(true)->size('little'),
TableColumn::key('actions')->button()->actions(['view', 'edit', 'visible', 'evidence', 'delete']),
```

- **`image` (2ª colonna)**: colonna virtuale (nessuna colonna DB `image`); sicura
  con `SSP::complex()` che fa `SELECT *` — il formatter usa `$row['id']` per la
  copertina (`ImmobilePresenter::coverImage`). Non ordinabile/ricercabile.
- **HTML pesante in helper puri** (leggibilità): le closure `image`/`nome`
  delegano a helper globali `immobiliListImage(array $row): string` e
  `immobiliListNome(array $row): string` in `src/helpers.php` (spostando lì il
  markup miniatura + il nome con sottotitolo e link "Visualizza sul sito"). Le
  closure di `prezzo`/`superficie` chiamano gli helper puri già esistenti.
- **`nome` senza miniatura** (spostata nella colonna `image`).
- **Rimuovere** `config/formatters.php` e la chiave `boot.files` da `module.json`
  (la logica pura resta negli helper `immobili*`). Nessuna registrazione per nome
  residua.
- **Correzioni alle righe rotte introdotte dall'utente**: `->image()->link('view')`
  sostituito dal formatter inline; l'azione `detail_page => ['label' => …]` rimossa
  dalla lista `actions` (vedi fuori scope).

## Testing / verifica

- **Unit** `tests/Backend/Table/ColumnFormatterFromResourcesTest.php`: registra
  una resource fittizia con una colonna a formatter closure; `fromResources()` →
  `has('{slug}.{col}')` true e `call()` esegue la closure con la riga; nome
  esplicito ancora risolvibile; `reset()` azzera; nome non registrato → `''`.
- **Unit** `Column::formatter(Closure)` salva la closure in `toArray()['formatter']`.
- **Dispatch** `Field` con chiave derivata (estende `FieldFormatterTest`): format
  `['formatter' => '{slug}.{col}']` + closure registrata via `fromResources` →
  cella = output closure.
- Stile test = script PHP semplici (come il resto del repo).
- `php -l` su ogni file toccato; `composer dump-autoload` per classi nuove.
- **E2E nel sito**: lista immobili con formatter inline + immagine 2ª colonna;
  refresh AJAX (ordina/cerca/pagina) mantiene i formatter; `48 mq` / `€ …` / `/mese`.

## Consegna

Come il precedente formatter: commit sul branch `main` di `packages/app` + push su
origin (outward-facing, conferma utente), poi `composer update wonder-image/app`
nel sito. Le modifiche al modulo `immobili` restano nel working tree (le committa
l'utente).

## Fuori scope

- **Azione custom di riga** `detail_page` "Pagina Immobile": richiede un'estensione
  separata (link custom nel menu «…»), non i formatter. Segnalata, non inclusa.
- **Pagina `view` backend**: il suo aspetto è affrontato a parte via
  `ImmobileResource::customShowViewPath()` → `view/pages/backend/immobili/show.php`.
- **Errore i18n PDF** `pages.immobili.pdf.facts.riferimento`: chiave di traduzione
  mancante, indipendente.
- Nessuna closure inviata al client (vincolo AJAX); nessun refactor del meccanismo
  `->function()`.
