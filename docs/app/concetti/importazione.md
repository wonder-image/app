# Caricamento da file

Il core ha un motore generico per caricare righe da un file `.xlsx` o `.csv`:
file → abbinamento delle colonne → anteprima → scrittura a blocchi →
annullamento. Non conosce nessuna tabella: le tocca solo il **tracciato**
(`Wonder\App\Import\Layout`) che un modulo o il sito registra.

## Il percorso

1. **File.** `Importer::upload()` legge il file con `Reader` (BOM, separatore
   `,` `;` o tabulazione, Windows-1252, al massimo `Reader::MAX_ROWS` righe;
   i numeri interi di Excel restano stringhe di cifre, così gli EAN non
   perdono zeri) e salva il lavoro in `import_jobs`.
2. **Colonne.** `Mapping::guess()` propone per ogni intestazione una colonna
   del tracciato: vincono le intestazioni del tracciato salvato scelto, poi
   quella del codice, poi la chiave e gli altri nomi (`Column::make($key,
   $header, $aliases)`). Il confronto ignora maiuscole, spazi, punteggiatura
   e accenti. Una colonna va a una sola intestazione. `Mapping::report()`
   dice quali intestazioni sono state riconosciute (con la colonna e il campo
   in cui finiscono, `Column::field()`), quali non si leggono e quali colonne
   obbligatorie (`Column::required()`) mancano: la pagina lo mostra in una
   tabella sopra l'abbinamento.
3. **Anteprima.** `Importer::map()` controlla l'abbinamento
   (`Layout::mappingErrors()`) e tutte le righe insieme (`Layout::validate()`):
   per ognuna l'azione prevista (`create`, `update`, `unchanged`), gli errori
   e gli avvisi, in `import_rows`. Nessuna modifica ai dati. Una colonna
   obbligatoria senza intestazione (`Mapping::missing()`) ferma qui il
   caricamento con l'errore `missing_column`.
4. **Scrittura.** `Importer::start()` parte solo se nessuna riga ha errori.
   `Importer::step()` scrive le righe a blocchi, una transazione per riga
   (`Layout::apply()`), sotto un `NamedLock` per lavoro: lo chiamano la pagina
   aperta (`POST /backend/app/config/import/{id}/step/`) e il task sempre
   attivo `wonder.import`, così chi chiude la pagina non ferma il caricamento.
5. **Annullamento.** Ogni riga scritta registra le sue modifiche (`Change`:
   `created`, `updated` con i valori di prima, `stock` col movimento) in
   `import_changes`; `Importer::undo()` le disfa dall'ultima alla prima in una
   transazione sola (`Layout::undo()`).

Il passo del file offre il **file di esempio** del tracciato in `.xlsx` e
`.csv` (`GET /backend/app/config/import/sample/?layout=&format=`, route
`import.sample`, stesso permesso della pagina): lo scrive `Sample` con le
intestazioni di `Layout::sampleColumns()` e le righe di
`Layout::sampleRows()`. Un esempio ricaricato così com'è deve passare
l'anteprima senza errori.

La pagina è `ImportResource` (`ImportResource::url(['layout' => 'catalog'])`),
fuori dal menu: la apre un bottone della lista che la usa, e il permesso lo
decide `Layout::permits($user)`.

## Scrivere un tracciato

```php
use Wonder\App\Import\{Change, Column, Context, Layout};

final class BrandLayout extends Layout
{
    public static function key(): string { return 'brands'; }
    public function label(): string { return 'Marchi'; }
    public function permits(object $user): bool { return BrandResource::allows($user->authority, 'update'); }
    public function backUrl(): ?string { return BrandResource::listUrl(); }

    public function columns(): array
    {
        return [
            Column::make('name', 'Nome', ['Marchio'])->required()->field('Nome del marchio'),
            Column::make('slug', 'Slug')->field('Indirizzo della pagina'),
        ];
    }

    public function sampleRows(): array
    {
        return [['name' => 'Esempio', 'slug' => 'esempio']];
    }

    public function validate(array $rows, Context $context): array { /* azione, errori, avvisi per riga */ }
    public function apply(array $data, Context $context): array { /* ['action' => ..., 'changes' => [Change, ...]] */ }
    public function undo(Change $change, Context $context): void { /* disfa una Change */ }
}
```

Le celle vuote vanno trattate come «non toccare». `Context::remember()` tiene
in memoria le letture ripetute (marchi, categorie) e `Context::warn()` aggiunge
un avviso alla riga. `Layout::message()` traduce in testo le eccezioni del
modulo; quelle che non conosce diventano un messaggio generico.

## Registrare un tracciato

Il core non ne ha (`Layouts::CORE = []`). Un modulo li aggiunge nella sua
config, il sito ha l'ultima parola:

```php
// config del modulo
'import' => ['layouts' => ['brands' => BrandLayout::class]],

// sito
Layouts::set('brands', MyBrandLayout::class); // sostituisce
Layouts::set('brands', false);                // toglie
```

Un tracciato che non si costruisce va nel log e si salta.

## Tracciati file

In Set-up → **Tracciati file** (`ImportLayoutResource`, solo admin) si salva
per un tipo l'elenco delle colonne, in ordine e con l'intestazione voluta
(`import_layouts`, `import_layout_columns`). Uno per tipo è il predefinito:
salvarne uno predefinito lo toglie agli altri. `Profile::for($layout)` dà le
colonne del tracciato salvato (o null): l'export del modulo le usa per scrivere
il file, il caricamento le propone per prime nell'abbinamento.

## Test

```bash
php tests/App/ImportEngineTest.php
```
