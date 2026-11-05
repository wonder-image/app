<?php
/** php tests/App/Dataset/DatasetTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Dataset\Column;
use Wonder\App\Dataset\Exportable;
use Wonder\App\Dataset\ResourceSource;
use Wonder\App\Dataset\Sources;
use Wonder\App\Dataset\Type;
use Wonder\App\Import\Change;
use Wonder\App\Import\Context;
use Wonder\App\Import\Layout;
use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\ResourceDownloadController;

final class DatasetTestModel extends Model
{
    public static string $table = 'dataset_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

class DatasetTestResource extends Resource
{
    public static string $model = DatasetTestModel::class;
    public static string $path = 'dataset-test';

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->download(['csv'])
            ->fileColumns([
                Column::make('sku', 'Codice'),
                Column::make('price', 'Prezzo')->type(Type::Money),
                ['field' => 'sku', 'label' => 'Codice bis'],
                ['label' => 'Nome completo', 'value' => fn (array $row): string => $row['name'].' '.$row['surname']],
                ['field' => 'when', 'label' => 'Quando', 'value' => 'date'],
            ]);
    }
}

final class DatasetTestSource extends ResourceSource
{
    public function rows(): iterable
    {
        return [
            ['sku' => 'A1', 'price' => '12.5', 'name' => 'Mario', 'surname' => 'Rossi', 'date' => '2026-01-02'],
        ];
    }
}

final class DatasetTestLayout extends Layout
{
    public static function key(): string { return 'dataset-test-layout'; }
    public function label(): string { return 'Prova'; }
    public function permits(object $user): bool { return true; }
    public function columns(): array { return [Column::make('sku', 'Codice')->required()]; }
    public function validate(array $rows, Context $context): array { return []; }
    public function apply(array $data, Context $context): array { return []; }
    public function undo(Change $change, Context $context): void {}
}

final class DatasetTestExport implements Exportable
{
    public function id(): string { return 'dataset-test-export'; }
    public function label(): string { return 'Export di prova'; }
    public function columns(): array { return [Column::make('id', 'ID')]; }
    public function rows(): iterable { return [['id' => 1]]; }
    public function allows(?object $user, string $action): bool { return $action === 'export'; }
}

// Tipi: quello che esce si rilegge uguale.
check('Money esce con due decimali e il punto', fn () => Type::Money->format('12,5') === '12.50');
check('Number legge 1.234,56 e 1,234.56', fn () => Type::Number->parse('1.234,56') === 1234.56 && Type::Number->parse('1,234.56') === 1234.56);
check('Number non mette zeri inutili', fn () => Type::Number->format(3.10) === '3.1' && Type::Number->format(7) === '7');
check('Date legge la data italiana', fn () => Type::Date->parse('05/11/2026') === '2026-11-05');
check('Date scarta date impossibili e zeri', fn () => Type::Date->parse('31/02/2026') === null && Type::Date->format('0000-00-00') === '');
check('Boolean capisce sì / no', fn () => Type::Boolean->parse('Sì') === true && Type::Boolean->parse('no') === false);
check('List da JSON o con |', fn () => Type::List->format('["a","b"]') === 'a|b' && Type::List->parse('a | b|') === ['a', 'b']);
check('una cella vuota è null (lista vuota per List)', fn () => Type::Money->parse(' ') === null && Type::List->parse('') === []);

// Colonne.
check('withHeader tiene la vecchia intestazione tra gli alias', function () {
    $column = Column::make('sku', 'Codice', ['SKU'])->withHeader('Cod. articolo');

    return $column->header === 'Cod. articolo' && $column->aliases === ['SKU', 'Codice'];
});
check('withHeader vuota lascia la colonna com\'è', function () {
    $column = Column::make('sku', 'Codice');

    return $column->withHeader('  ') === $column;
});
check('value stringa è un campo, closure una funzione della riga', function () {
    $row = ['date' => 'x', 'a' => 1, 'b' => 2];

    return Column::make('k', 'K')->value('date')->read($row) === 'x'
        && Column::make('k', 'K')->value(fn (array $r): int => $r['a'] + $r['b'])->read($row) === 3;
});
check('export formatta col tipo, senza tipo passa così com\'è', function () {
    $row = ['p' => '3', 'q' => 4];

    return Column::make('p', 'P')->type('money')->export($row) === '3.00' && Column::make('q', 'Q')->export($row) === 4;
});

// Resource come fonte.
$source = new DatasetTestSource(DatasetTestResource::class);

check('le colonne della Resource hanno chiavi uniche', function () use ($source) {
    return array_map(fn (Column $c): string => $c->key, $source->columns()) === ['sku', 'price', 'sku_2', 'nome_completo', 'when'];
});
check('la colonna rinominata legge ancora il suo campo', function () use ($source) {
    return $source->columns()[2]->read(['sku' => 'A1']) === 'A1';
});
check('un value stringa non chiama una funzione globale', function () use ($source) {
    return $source->columns()[4]->read(['date' => '2026-01-02', 'when' => 'no']) === '2026-01-02';
});
check('una Resource serve solo all\'export', function () use ($source) {
    return $source->allows(null, 'export') && !$source->allows(null, 'import');
});

// Tracciato di caricamento come fonte.
check('un tracciato di caricamento serve solo all\'import e mai senza utente', function () {
    $layout = new DatasetTestLayout();

    return $layout->id() === 'dataset-test-layout'
        && $layout->allows(new stdClass(), 'import')
        && !$layout->allows(null, 'import')
        && !$layout->allows(new stdClass(), 'export');
});

check('un tracciato con la vecchia Import\Column si abbina e fa l\'esempio', function () {
    $old = \Wonder\App\Import\Column::make('sku', 'Codice', ['SKU'])->required()->withHeader('Cod.');
    $mapping = \Wonder\App\Import\Mapping::guess(['sku', 'Altro'], [$old]);
    $sample = \Wonder\App\Import\Sample::table(new DatasetTestLayout());

    return $old instanceof \Wonder\App\Import\Column && $old->required
        && $mapping === [0 => 'sku', 1 => null]
        && $sample === [['Codice']];
});

// Registro.
check('Sources aggiunge da config e da set(), false toglie', function () {
    Sources::reset();
    $configs = [['dataset' => ['sources' => ['dataset-test-export' => DatasetTestExport::class]]]];
    $first = isset(Sources::all($configs)['dataset-test-export']);

    Sources::set('dataset-test-export', false);
    $removed = !isset(Sources::all($configs)['dataset-test-export']);

    Sources::set('dataset-test-res', DatasetTestResource::class);
    $resource = Sources::find('dataset-test-res', $configs);
    Sources::reset();

    return $first && $removed && $resource instanceof ResourceSource && $resource->id() === 'dataset-test';
});
check('Sources::allowed filtra per azione', function () {
    Sources::reset();
    Sources::set('dataset-test-export', new DatasetTestExport());
    $export = isset(Sources::allowed(null, 'export', [])['dataset-test-export']);
    $import = isset(Sources::allowed(null, 'import', [])['dataset-test-export']);
    Sources::reset();

    return $export && !$import;
});

// Il file dell'export.
check('matrix: intestazione e righe dalle colonne', function () use ($source) {
    $reflection = new ReflectionClass(ResourceDownloadController::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('source')->setValue($controller, $source);

    return $controller->matrix() === [
        ['Codice', 'Prezzo', 'Codice bis', 'Nome completo', 'Quando'],
        ['A1', '12.50', 'A1', 'Mario Rossi', '2026-01-02'],
    ];
});

// Schema della tabella.
check('importLayout salva tracciato ed etichetta, null lo toglie', function () {
    $layout = TableLayoutSchema::for(DatasetTestResource::class)->importLayout('dataset-test-layout', 'Carica');
    $ok = ($layout->all()['import'] ?? null) === ['layout' => 'dataset-test-layout', 'label' => 'Carica'];

    $all = $layout->importLayout(null)->all();

    return $ok && array_key_exists('import', $all) && $all['import'] === null;
});
check('downloadColumns resta un alias di fileColumns', function () {
    $a = TableLayoutSchema::for(DatasetTestResource::class)->downloadColumns(['sku'])->all()['download']['columns'] ?? null;
    $b = TableLayoutSchema::for(DatasetTestResource::class)->fileColumns(['sku'])->all()['download']['columns'] ?? null;

    return $a !== null && $a === $b;
});
check('fileRows salva le righe dell\'export', function () {
    $rows = fn (): array => [];

    return (TableLayoutSchema::for(DatasetTestResource::class)->fileRows($rows)->all()['download']['rows'] ?? null) === $rows;
});

summary();
