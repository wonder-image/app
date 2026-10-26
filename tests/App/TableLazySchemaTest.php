<?php
/**
 * php tests/App/TableLazySchemaTest.php
 *
 * Schemi `resource:*` registrati lazy da app/config/app/table.php: formSchema()
 * non gira al boot, il primo accesso lo calcola una volta sola, le chiavi
 * restano riconosciute (list-table compreso) e prepare() non cambia.
 */
declare(strict_types=1);

namespace Wonder\Tests\TableLazySchema;

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\Table;
use Wonder\Backend\Table\ListTableRequest;
use Wonder\Backend\Table\TableAuthorization;

require __DIR__ . '/../harness.php';
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/function/file/dir.php';
require_once __DIR__ . '/../../app/function/sql.php';
require_once __DIR__ . '/../../app/function/string/function.php';

final class LazyModel extends Model
{
    public static string $table = 'zz_lazy_items';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class LazyResource extends Resource
{
    public static string $model = LazyModel::class;
    public static string $path = 'zz-lazy-items';
    public static int $calls = 0;

    // Costoso in produzione (opzioni dal DB): qui conta le chiamate.
    public static function formSchema(): array
    {
        self::$calls++;

        return [
            FormField::key('title')->text(),
            FormField::key('icon')->text(),
            FormField::key('cover')->file(),
        ];
    }
}

$base = sys_get_temp_dir().'/wi-table-lazy-'.bin2hex(random_bytes(4));
@mkdir($base.'/app/build/table', 0777, true);
@mkdir($base.'/custom/build/table', 0777, true);
@mkdir($base.'/custom/config/resource', 0777, true);
file_put_contents(
    $base.'/custom/config/resource/resources.php',
    '<?php return '.var_export([LazyResource::class], true).';'
);
register_shutdown_function(static fn () => exec('rm -rf '.escapeshellarg($base)));

$GLOBALS['ROOT'] = $base;
ResourceRegistry::reset();

// Boot come in app/config/app.php.
(static function () use ($base): void {
    $ROOT = $base;
    $ROOT_APP = $base.'/app';
    $TABLE = (object) [];
    require __DIR__ . '/../../app/config/app/table.php';
})();

$key = LazyResource::prepareSchemaName();

check('boot: formSchema() della Resource non viene eseguito', fn () => LazyResource::$calls === 0);

check('boot: chiave lazy riconosciuta da has() senza risolverla', fn () =>
    Table::has($key) && Table::has(strtoupper($key)) && LazyResource::$calls === 0);

check('boot: in $list la chiave lazy c\'è ed è vuota', fn () =>
    array_key_exists($key, Table::$list) && Table::$list[$key] === [] && LazyResource::$calls === 0);

check('list-table: tabella e schema lazy accettati senza risolverli', fn () =>
    ListTableRequest::table($key) === $key
    && ListTableRequest::schema($key) === $key
    && ListTableRequest::table('resource:non-esiste') === null
    && LazyResource::$calls === 0);

check('primo accesso: schema() risolve e memorizza in $list', function () use ($key) {
    $schema = Table::key($key)->schema();

    return LazyResource::$calls === 1
        && array_key_exists('title', $schema)
        && Table::$list[$key] === $schema;
});

check('accessi successivi: nessun nuovo calcolo', function () use ($key) {
    Table::key($key)->schema();
    Table::key($key)->columns();
    Table::key($key)->hasColumn('title');

    return LazyResource::$calls === 1;
});

check('prepareFor(): chiave lazy e resolver con la stessa funzione danno lo stesso formToArray() di prepareSchema() diretto', function () use ($key) {
    $GLOBALS['NAME'] = (object) ['table' => 'zz_lazy_items', 'folder' => '', 'schema' => $key];
    $post = ['title' => 'Ciao', 'icon' => 'x', 'altro' => 'ignorato'];
    $eager = formToArray('zz_lazy_items', $post, LazyResource::prepareSchema(), null);

    Table::key('resource:zz-lazy-copia')->setSchemaResolver(static fn (): array => LazyResource::prepareSchema());

    return Table::key($key)->prepareFor('zz_lazy_items', $post) === $eager
        && Table::key('resource:zz-lazy-copia')->prepareFor('zz_lazy_items', $post) === $eager
        && $eager === ['title' => 'Ciao', 'icon' => 'x'];
});

check('prepare() su chiave lazy mai letta la risolve', function () {
    $hits = 0;
    Table::key('zz_lazy_prepare')->setSchemaResolver(static function () use (&$hits): array {
        $hits++;
        return ['name' => []];
    });

    return Table::key('zz_lazy_prepare')->prepare(['name' => 'a', 'x' => 'b']) === ['name' => 'a'] && $hits === 1;
});

check('TableAuthorization: colonne lette dallo schema lazy', function () {
    $hits = 0;
    Table::key('zz_lazy_auth')->setSchemaResolver(static function () use (&$hits): array {
        $hits++;
        return ['icon' => [], 'cover' => ['input' => ['format' => ['file' => true]]]];
    });

    return TableAuthorization::iconTable('zz_lazy_auth') === 'zz_lazy_auth'
        && TableAuthorization::fileColumn('zz_lazy_auth', 'cover') === ['zz_lazy_auth', 'cover']
        && TableAuthorization::fileColumn('zz_lazy_auth', 'icon') === null
        && $hits === 1;
});

check('setSchema() dopo un resolver lo sostituisce', function () {
    $hits = 0;
    Table::key('zz_lazy_override')->setSchemaResolver(static function () use (&$hits): array {
        $hits++;
        return ['a' => []];
    });
    Table::key('zz_lazy_override')->setSchema(['b' => []]);

    return Table::key('zz_lazy_override')->schema() === ['b' => []] && $hits === 0;
});

check('resolver che lancia: si riprova al prossimo accesso', function () {
    $hits = 0;
    Table::key('zz_lazy_throw')->setSchemaResolver(static function () use (&$hits): array {
        if (++$hits === 1) {
            throw new \RuntimeException('db giù');
        }
        return ['ok' => []];
    });

    try {
        Table::key('zz_lazy_throw')->schema();
        return false;
    } catch (\RuntimeException) {
    }

    return Table::key('zz_lazy_throw')->schema() === ['ok' => []] && $hits === 2;
});

check('resolver che rilegge la propria chiave: LogicException, niente memorizzato', function () {
    Table::key('zz_lazy_self')->setSchemaResolver(static fn (): array =>
        ['inner' => Table::key('zz_lazy_self')->schema()]);

    try {
        Table::key('zz_lazy_self')->schema();
        return false;
    } catch (\LogicException $error) {
        $message = $error->getMessage();
    }

    return str_contains($message, 'zz_lazy_self → zz_lazy_self')
        && Table::has('zz_lazy_self')
        && Table::$list['zz_lazy_self'] === [];
});

check('rientro A → B → A intercettato da un resolver: nessuno dei due schemi memorizzato', function () {
    Table::key('zz_lazy_a')->setSchemaResolver(static fn (): array =>
        ['a' => [], 'b' => Table::key('zz_lazy_b')->schema()]);
    // Come safeFormFieldsByKey(): l'eccezione del rientro viene inghiottita.
    Table::key('zz_lazy_b')->setSchemaResolver(static function (): array {
        try {
            return ['b' => [], 'a' => Table::key('zz_lazy_a')->schema()];
        } catch (\Throwable) {
            return ['b' => []];
        }
    });

    try {
        Table::key('zz_lazy_a')->schema();
        return false;
    } catch (\LogicException) {
    }

    return Table::$list['zz_lazy_a'] === [] && Table::$list['zz_lazy_b'] === [];
});

check('has() dipende solo da $list: dopo un\'assegnazione intera la chiave lazy sparisce', function () {
    $previous = Table::$list;
    $hits = 0;
    Table::key('zz_lazy_gone')->setSchemaResolver(static function () use (&$hits): array {
        $hits++;
        return ['x' => []];
    });

    Table::$list = ['altra' => []];
    $ok = !Table::has('zz_lazy_gone') && Table::key('zz_lazy_gone')->schema() === [] && $hits === 0;
    Table::$list = $previous;

    return $ok;
});

summary();
