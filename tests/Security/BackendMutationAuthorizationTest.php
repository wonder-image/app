<?php
/** php tests/Security/BackendMutationAuthorizationTest.php */
declare(strict_types=1);

require dirname(__DIR__).'/harness.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';

# vendor/ puo essere un symlink a un altro checkout: le classi Wonder vengono
# sempre da questo albero.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Wonder\\')) {
        $file = dirname(__DIR__, 2).'/class/'.str_replace('\\', '/', substr($class, 7)).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);

use Wonder\App\{ Model, Resource };
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\Backend\Table\TableAuthorization;

# Core-3: mutazioni backend su tabella scelta dal client (change/boolean,
# delete, move, file/delete, file/move, authority).

final class MutationTestModel extends Model
{
    public static string $table = 'mutation_admin_table';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

/** update/delete solo admin. */
final class MutationAdminResource extends Resource
{
    public static string $model = MutationTestModel::class;

    public static function permission(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['update', 'delete'], ['admin']);
    }
}

/** update per editor, delete solo admin. */
final class MutationEditorResource extends Resource
{
    public static string $model = MutationTestModel::class;

    public static function permission(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend('update', ['admin', 'editor'])
            ->backend('delete', ['admin']);
    }
}

/** Default della PermissionSchema: nessuna authority richiesta. */
final class MutationOpenResource extends Resource
{
    public static string $model = MutationTestModel::class;
}

$map = [
    'mutation_admin_table' => MutationAdminResource::class,
    'mutation_editor_table' => MutationEditorResource::class,
    'mutation_open_table' => MutationOpenResource::class,
    'user' => MutationAdminResource::class,
];
$auth = new TableAuthorization(static fn (string $table): ?string => $map[$table] ?? null);
$legacyOnly = new TableAuthorization(static fn (string $table): ?string => null);

$admin = (object) ['id' => 1, 'authority' => ['admin']];
$editor = (object) ['id' => 2, 'authority' => ['editor']];

# Tabelle con Resource

check('Editor su tabella con update/delete admin: negato (=> 403)', function () use ($auth) {
    return !$auth->allows('mutation_admin_table', TableAuthorization::UPDATE, ['editor'])
        && !$auth->allows('mutation_admin_table', TableAuthorization::DELETE, ['editor']);
});

check('Admin su tabella con update/delete admin: ammesso', function () use ($auth) {
    return $auth->allows('mutation_admin_table', TableAuthorization::UPDATE, ['admin'])
        && $auth->allows('mutation_admin_table', TableAuthorization::DELETE, ['admin']);
});

check('update e delete sono azioni distinte della PermissionSchema', function () use ($auth) {
    return $auth->allows('mutation_editor_table', TableAuthorization::UPDATE, ['editor'])
        && !$auth->allows('mutation_editor_table', TableAuthorization::DELETE, ['editor']);
});

check('Resource senza authority richieste: qualsiasi utente backend (regola di Resource::allows)', function () use ($auth) {
    return $auth->allows('mutation_open_table', TableAuthorization::UPDATE, ['editor']);
});

check('Azione sconosciuta o tabella vuota: negato', function () use ($auth) {
    return !$auth->allows('mutation_admin_table', 'list', ['admin'])
        && !$auth->allows('', TableAuthorization::UPDATE, ['admin']);
});

# Tabelle legacy

check('Tabella legacy senza Resource: solo admin', function () use ($legacyOnly) {
    return $legacyOnly->allows('legacy_table', TableAuthorization::UPDATE, ['admin'])
        && !$legacyOnly->allows('legacy_table', TableAuthorization::UPDATE, ['editor'])
        && !$legacyOnly->allows('legacy_table', TableAuthorization::DELETE, [])
        && !$legacyOnly->allows('legacy_table', TableAuthorization::DELETE, ['administrator']);
});

check('authoritiesOf(): array, JSON e oggetti non validi', function () {
    return TableAuthorization::authoritiesOf((object) ['authority' => ['admin', ' editor ', '']]) === ['admin', 'editor']
        && TableAuthorization::authoritiesOf((object) ['authority' => '["admin"]']) === ['admin']
        && TableAuthorization::authoritiesOf(null) === [];
});

# authority.php

check('authority: la propria riga e rifiutata anche per admin', function () use ($auth, $admin) {
    return !$auth->allowsAuthorityChange('user', 1, $admin);
});

check('authority: tabella diversa da user rifiutata', function () use ($auth, $admin) {
    return !$auth->allowsAuthorityChange('mutation_admin_table', 5, $admin)
        && !$auth->allowsAuthorityChange('users', 5, $admin);
});

check('authority: admin su un altro utente ammesso, editor no', function () use ($auth, $admin, $editor) {
    return $auth->allowsAuthorityChange('user', 5, $admin)
        && !$auth->allowsAuthorityChange('user', 5, $editor)
        && !$auth->allowsAuthorityChange('user', 5, null);
});

check('authority: rotta permit([\'admin\'])', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/config/routes/route.api.php');

    return (bool) preg_match("~/authority/'.*?->name\\('authority'\\)\\s*->guarded\\(\\)\\s*->permit\\(\\['admin'\\]\\);~s", $source);
});

# file/delete e file/move: tabella, colonna, cartella

$registry = [
    'user' => [
        'name' => ['input' => ['format' => []]],
        'profile_picture' => ['input' => ['format' => ['file' => true, 'dir' => '/profile-picture/']]],
    ],
];

check('fileColumn: tabella sconosciuta => null (422)', function () use ($registry) {
    return TableAuthorization::fileColumn('mysql.user', 'profile_picture', $registry) === null
        && TableAuthorization::fileColumn('', 'profile_picture', $registry) === null
        && TableAuthorization::fileColumn(['user'], 'profile_picture', $registry) === null;
});

check('fileColumn: colonna non file o assente => null (422)', function () use ($registry) {
    return TableAuthorization::fileColumn('user', 'name', $registry) === null
        && TableAuthorization::fileColumn('user', 'password', $registry) === null
        && TableAuthorization::fileColumn('user', '', $registry) === null;
});

check('fileColumn: colonna file => tabella canonica e colonna', function () use ($registry) {
    return TableAuthorization::fileColumn('USER', 'profile_picture', $registry) === ['user', 'profile_picture'];
});

check('uploadFolder: .. e percorsi non semplici rifiutati', function () {
    foreach (['../config', '/a/../../etc/', 'a/./b', "a\0b", 'a\\b', 'a//b', 'a b'] as $folder) {
        if (TableAuthorization::uploadFolder($folder) !== null) {
            return false;
        }
    }

    return true;
});

check('uploadFolder: cartella del client normalizzata sotto upload', function () {
    return TableAuthorization::uploadFolder('/profile-picture/') === 'profile-picture'
        && TableAuthorization::uploadFolder('blog/2026') === 'blog/2026'
        && TableAuthorization::uploadFolder('') === ''
        && TableAuthorization::uploadFolder('/') === '';
});

# Handler: ordine dei controlli e nessuna query prima dell'autorizzazione

$handlers = ['change-boolean', 'delete', 'move', 'authority', 'file-delete', 'file-move'];

check('Handler: autorizzazione prima di selectDatabase/sqlSelect/sqlColumnExists', function () use ($handlers) {
    foreach ($handlers as $name) {
        $source = (string) file_get_contents(dirname(__DIR__, 2)."/app/http/api/backend/{$name}.php");
        $guard = strpos($source, 'TableAuthorization::make()->');

        if ($guard === false) {
            return false;
        }

        foreach (['ApiRequest::selectDatabase(', 'sqlSelect(', 'sqlColumnExists(', 'sqlModify('] as $call) {
            $position = strpos($source, $call);

            if ($position !== false && $position < $guard) {
                return false;
            }
        }
    }

    return true;
});

check('Handler file: tabella registrata, poi autorizzazione, poi schema (fileColumn/iconTable)', function () {
    foreach (['file-delete' => 'fileColumn(', 'file-move' => 'fileColumn(', 'delete-icon' => 'iconTable('] as $name => $schemaCall) {
        $source = (string) file_get_contents(dirname(__DIR__, 2)."/app/http/api/backend/{$name}.php");
        $table = strpos($source, 'ListTableRequest::table(');
        $guard = strpos($source, 'TableAuthorization::make()->');
        $schema = strpos($source, 'TableAuthorization::'.$schemaCall);

        if ($table === false || $guard === false || $schema === false || !($table < $guard && $guard < $schema)) {
            return false;
        }
    }

    $delete = (string) file_get_contents(dirname(__DIR__, 2).'/app/http/api/backend/file-delete.php');

    return str_contains($delete, 'TableAuthorization::uploadFolder($folder)')
        && !str_contains($delete, "trim(\$folder, '/')");
});

/**
 * Esegue un handler in un processo separato, senza bootstrap: le funzioni
 * SQL e il pool sono finti e chiudono il processo se vengono raggiunti.
 */
$runHandler = static function (string $name, array $post, array $authority, string $setup = '') use ($registry): string {
    $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);
    $classes = var_export(dirname(__DIR__, 2).'/class/', true);
    $file = var_export(dirname(__DIR__, 2)."/app/http/api/backend/{$name}.php", true);
    $postExport = var_export($post, true);
    $registryExport = var_export($registry, true);
    $authorityExport = var_export($authority, true);
    $script = tempnam(sys_get_temp_dir(), 'wi-mutation-');

    if (!is_string($script)) {
        return '';
    }

    $source = <<<PHP
<?php
require {$autoload};
spl_autoload_register(static function (string \$class): void {
    if (str_starts_with(\$class, 'Wonder\\\\')) {
        \$file = {$classes}.str_replace('\\\\', '/', substr(\$class, 7)).'.php';
        if (is_file(\$file)) { require \$file; }
    }
}, true, true);
\Wonder\App\Table::\$list = {$registryExport};
{$setup}
(function () {
    \$property = new ReflectionProperty(\Wonder\App\ResourceRegistry::class, 'resources');
    \$property->setValue(null, []);
})();
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST = {$postExport};
\$USER = (object) ['id' => 1, 'authority' => {$authorityExport}];
\$GLOBALS['wiReached'] = [];
function sqlSelect(...\$args) { \$GLOBALS['wiReached'][] = 'sqlSelect'; exit; }
function sqlModify(...\$args) { \$GLOBALS['wiReached'][] = 'sqlModify'; exit; }
function sqlColumnExists(...\$args) { \$GLOBALS['wiReached'][] = 'sqlColumnExists'; exit; }
register_shutdown_function(function (): void {
    echo '|status='.http_response_code().'|reached='.implode(',', \$GLOBALS['wiReached']);
});
require {$file};
PHP;

    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output);
    unlink($script);

    return implode("\n", $output);
};

check('file-delete: tabella sconosciuta => 422 generico, nessuna query', function () use ($runHandler) {
    $out = $runHandler('file-delete', ['table' => 'mysql.user', 'column' => 'x', 'row_id' => 1, 'file_id' => 0, 'folder' => ''], ['admin']);

    return str_contains($out, '|status=422|reached=') && !preg_match('/reached=\S/', $out)
        && str_contains($out, 'Parametri non validi.') && !str_contains(strtolower($out), 'sql');
});

check('file-delete: colonna non file => 422, nessuna query', function () use ($runHandler) {
    $out = $runHandler('file-delete', ['table' => 'user', 'column' => 'name', 'row_id' => 1, 'file_id' => 0, 'folder' => ''], ['admin']);

    return str_contains($out, '|status=422|reached=') && !preg_match('/reached=\S/', $out);
});

check('file-delete: folder con .. => 422, nessuna query', function () use ($runHandler) {
    $out = $runHandler('file-delete', ['table' => 'user', 'column' => 'profile_picture', 'row_id' => 1, 'file_id' => 0, 'folder' => '/../../config/'], ['admin']);

    return str_contains($out, '|status=422|reached=') && !preg_match('/reached=\S/', $out);
});

check('file-move: tabella sconosciuta => 422, nessuna query', function () use ($runHandler) {
    $out = $runHandler('file-move', ['table' => 'mysql.user', 'column' => 'x', 'row_id' => 1, 'file_id' => 0, 'action' => 'down'], ['admin']);

    return str_contains($out, '|status=422|reached=') && !preg_match('/reached=\S/', $out);
});

check('file-delete: tabella legacy, utente non admin => 403 prima della query', function () use ($runHandler) {
    $out = $runHandler('file-delete', ['table' => 'user', 'column' => 'profile_picture', 'row_id' => 1, 'file_id' => 0, 'folder' => '/profile-picture/'], ['editor']);

    return str_contains($out, '|status=403|reached=') && !preg_match('/reached=\S/', $out);
});

check('file-delete: admin con input valido arriva alla query', function () use ($runHandler) {
    $out = $runHandler('file-delete', ['table' => 'user', 'column' => 'profile_picture', 'row_id' => 1, 'file_id' => 0, 'folder' => '/profile-picture/'], ['admin']);

    return str_ends_with($out, '|reached=sqlSelect');
});

// Schema lazy che costa query (formSchema): senza permesso non deve girare.
$lazySetup = <<<'SETUP'
\Wonder\App\Table::key('zz_lazy_files')->setSchemaResolver(static function (): array {
    echo 'RESOLVED';
    return ['icon' => [], 'cover' => ['input' => ['format' => ['file' => true]]]];
});
SETUP;

check('file-delete / file-move / delete-icon: editor su schema lazy => 403, resolver mai eseguito', function () use ($runHandler, $lazySetup) {
    $cases = [
        ['file-delete', ['table' => 'zz_lazy_files', 'column' => 'cover', 'row_id' => 1, 'file_id' => 0, 'folder' => '']],
        ['file-move', ['table' => 'zz_lazy_files', 'column' => 'cover', 'row_id' => 1, 'file_id' => 0, 'action' => 'down']],
        ['delete-icon', ['table' => 'zz_lazy_files', 'id' => 1, 'folder' => 'icone']],
    ];

    foreach ($cases as [$name, $post]) {
        $out = $runHandler($name, $post, ['editor'], $lazySetup);

        if (!str_contains($out, '|status=403|reached=') || preg_match('/reached=\S/', $out) || str_contains($out, 'RESOLVED')) {
            return false;
        }
    }

    return true;
});

check('file-delete / file-move / delete-icon: admin su schema lazy => resolver eseguito, poi la query', function () use ($runHandler, $lazySetup) {
    $cases = [
        ['file-delete', ['table' => 'zz_lazy_files', 'column' => 'cover', 'row_id' => 1, 'file_id' => 0, 'folder' => '']],
        ['file-move', ['table' => 'zz_lazy_files', 'column' => 'cover', 'row_id' => 1, 'file_id' => 0, 'action' => 'down']],
        ['delete-icon', ['table' => 'zz_lazy_files', 'id' => 1, 'folder' => 'icone']],
    ];

    foreach ($cases as [$name, $post]) {
        $out = $runHandler($name, $post, ['admin'], $lazySetup);

        if (substr_count($out, 'RESOLVED') !== 1 || !str_ends_with($out, '|reached=sqlSelect')) {
            return false;
        }
    }

    return true;
});

check('change-boolean / delete / move su tabella legacy: editor => 403 senza query', function () use ($runHandler) {
    $cases = [
        ['change-boolean', ['table' => 'legacy_table', 'column' => 'active', 'id' => 3]],
        ['delete', ['table' => 'legacy_table', 'id' => 3]],
        ['move', ['table' => 'legacy_table', 'id' => 3, 'action' => 'up']],
    ];

    foreach ($cases as [$name, $post]) {
        $out = $runHandler($name, $post, ['editor']);

        if (!str_contains($out, '|status=403|reached=') || preg_match('/reached=\S/', $out)) {
            return false;
        }
    }

    return true;
});

check('authority: la propria riga => 403 senza query', function () use ($runHandler) {
    $out = $runHandler('authority', ['table' => 'user', 'id' => 1, 'authority' => 'admin'], ['admin']);

    return str_contains($out, '|status=403|reached=') && !preg_match('/reached=\S/', $out);
});

check('authority: tabella non user => 403 senza query', function () use ($runHandler) {
    $out = $runHandler('authority', ['table' => 'legacy_table', 'id' => 7, 'authority' => 'admin'], ['admin']);

    return str_contains($out, '|status=403|reached=') && !preg_match('/reached=\S/', $out);
});

summary();
