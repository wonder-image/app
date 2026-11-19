<?php
/**
 * php tests/App/Support/SyncClassificationTest.php
 *
 * Ogni tabella nota al framework ha una sola classe di sincronizzazione
 * (code / content / environment): fallisce se ne trova una non classificata o
 * classificata due volte. Il resto dei casi dimostra che il controllo sa
 * davvero fallire (Model finti, anche in un ROOT temporaneo come un sito).
 */
declare(strict_types=1);

namespace Wonder\Tests\SyncClassification;

use Wonder\App\Model;
use Wonder\App\ModelRegistry;
use Wonder\App\Support\SyncClass;
use Wonder\App\Support\SyncClassification;
use Wonder\App\Support\SyncSchema;
use Wonder\App\Support\TableSync;

require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../vendor/autoload.php';

abstract class FixtureModel extends Model
{
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class Unclassified extends FixtureModel { public static string $table = 'zz_sc_unclassified'; }

final class ContentA extends FixtureModel
{
    public static string $table = 'zz_sc_shared';
    public static function syncClass(): ?SyncClass { return SyncSchema::content(); }
}

final class EnvironmentB extends FixtureModel
{
    public static string $table = 'zz_sc_shared';
    public static function syncClass(): ?SyncClass { return SyncSchema::environment(); }
}

final class ContentC extends FixtureModel
{
    public static string $table = 'zz_sc_shared';
    public static function syncClass(): ?SyncClass { return SyncSchema::content(); }
}

class ParentContent extends FixtureModel
{
    public static string $table = 'zz_sc_extended';
    public static function syncClass(): ?SyncClass { return SyncSchema::content(); }
}

final class ChildContent extends ParentContent {}

final class EnvironmentWithSync extends FixtureModel
{
    public static string $table = 'zz_sc_env_sync';
    public static function syncClass(): ?SyncClass { return SyncSchema::environment(); }
    public static function syncSchema(): ?SyncSchema { return SyncSchema::multiRow(); }
}

final class CodeWithSync extends FixtureModel
{
    public static string $table = 'zz_sc_code_sync';
    public static function syncClass(): ?SyncClass { return SyncSchema::code(); }
    public static function syncSchema(): ?SyncSchema { return SyncSchema::multiRow(); }
}

// ---- il framework ---------------------------------------------------------

$previousRoot = $GLOBALS['ROOT'] ?? null;
unset($GLOBALS['ROOT']);
ModelRegistry::reset();
SyncClassification::reset();

check('framework: nessuna tabella non classificata, doppia o incoerente', function () {
    SyncClassification::assertComplete();
    return true;
});

check('framework: ogni tabella dei Model ha una classe (e le classi sono solo tre)', function () {
    $report = SyncClassification::report();
    $tables = array_keys(ModelRegistry::all());
    $missing = array_diff($tables, array_keys($report['classes']));
    return $tables !== [] && $missing === [];
});

check('framework: le tre classi si dividono le tabelle senza sovrapposizioni', function () {
    $all = [];
    foreach (SyncClass::cases() as $class) {
        foreach (SyncClassification::tables($class) as $table) {
            if (isset($all[$table])) {
                return false;
            }
            $all[$table] = true;
        }
    }
    return count($all) === count(SyncClassification::report()['classes']);
});

check('framework: le tabelle senza Model (app_update_runs) sono classificate', fn () =>
    SyncClassification::classOf('app_update_runs') === SyncClass::Environment
    && !isset(ModelRegistry::all()['app_update_runs']));

check('framework: segreti, utenti, sessioni e log non si copiano mai (environment)', function () {
    foreach (['security', 'api_users', 'oauth_clients', 'auth_remember', 'auth_log', 'consent_events', 'mail_log', 'scheduler_runs', 'scheduler_state', 'external_references'] as $table) {
        if (SyncClassification::classOf($table) !== SyncClass::Environment) {
            return false;
        }
    }
    return true;
});

check('framework: user e scheduler_schedules sono content (decisione: utenti anonimizzati, job copiati attivi)', fn () =>
    SyncClassification::classOf('user') === SyncClass::Content
    && SyncClassification::classOf('scheduler_schedules') === SyncClass::Content);

check('framework: user ha regole di anonimizzazione su dati personali e password, tutte valide', function () {
    $rules = SyncClassification::anonymizationOf('user');
    foreach (['name', 'surname', 'email', 'phone', 'username', 'password'] as $c) {
        if (!isset($rules[$c])) {
            return false;
        }
    }

    return SyncClassification::invalidAnonymization(SyncClassification::report()['classes']) === [];
});

check('anonimizzazione: colonna inesistente o tabella non content → non valida', function () {
    $env = SyncClassification::report()['classes'];
    $env['user'] = SyncClass::Environment;

    return in_array('user: regole di anonimizzazione su una tabella non content', SyncClassification::invalidAnonymization($env), true);
});

check('framework: css_* è codice; contenuto, sedi, media e traduzioni sono contenuto', function () {
    foreach (['css_alert', 'css_auth', 'css_color', 'css_default', 'css_dropdown', 'css_font', 'css_input', 'css_modal'] as $table) {
        if (SyncClassification::classOf($table) !== SyncClass::Code) {
            return false;
        }
    }
    foreach (['seo', 'society', 'society_locations', 'media', 'media_usages', 'logos', 'translations', 'translation_terms', 'legal_documents'] as $table) {
        if (SyncClassification::classOf($table) !== SyncClass::Content) {
            return false;
        }
    }
    return true;
});

check('framework: nessuna tabella environment finisce in shared/sync-data.json', function () {
    foreach (ModelRegistry::all() as $table => $modelClass) {
        if (SyncClassification::classOf($table) === SyncClass::Environment && $modelClass::syncSchema() !== null) {
            return false;
        }
    }
    return true;
});

check('regressione: shared/sync-data.json esporta le stesse 10 tabelle di prima', function () {
    $expected = ['css_alert', 'css_auth', 'css_color', 'css_default', 'css_dropdown', 'css_font', 'css_input', 'css_modal', 'seo', 'society_locations'];
    $found = array_keys(TableSync::discoverTables());
    sort($found);
    return $found === $expected;
});

// ---- il controllo sa fallire -----------------------------------------------

check('Model senza syncClass() → non classificato, assertComplete() lancia', function () {
    $report = SyncClassification::report([Unclassified::class, ContentC::class]);
    if (count($report['unclassified']) !== 1 || !str_starts_with($report['unclassified'][0], 'zz_sc_unclassified')) {
        return false;
    }
    try {
        SyncClassification::assertComplete([Unclassified::class]);
    } catch (\RuntimeException $e) {
        return str_contains($e->getMessage(), 'zz_sc_unclassified');
    }
    return false;
});

check('stessa tabella con due classi diverse → doppione', function () {
    $report = SyncClassification::report([ContentA::class, EnvironmentB::class]);
    return array_keys($report['duplicates']) === ['zz_sc_shared'] && !isset($report['classes']['zz_sc_shared']);
});

check('stessa tabella, stessa classe, due Model senza parentela → doppione', function () {
    $report = SyncClassification::report([ContentA::class, ContentC::class]);
    return array_keys($report['duplicates']) === ['zz_sc_shared'];
});

check('un Model che estende l\'altro (stessa classe) è un\'estensione, non un doppione', function () {
    $report = SyncClassification::report([ParentContent::class, ChildContent::class]);
    return $report['duplicates'] === [] && ($report['classes']['zz_sc_extended'] ?? null) === SyncClass::Content;
});

check('tabella senza Model dichiarata anche da un Model → doppione', function () {
    SyncClassification::reset();
    SyncClassification::register('zz_sc_shared', SyncClass::Content);
    $report = SyncClassification::report([ContentA::class]);
    SyncClassification::reset();
    return array_keys($report['duplicates']) === ['zz_sc_shared'];
});

check('register() classifica una tabella senza Model di un modulo o di un sito', function () {
    SyncClassification::reset();
    SyncClassification::register('zz_sc_module_table', SyncClass::Content);
    $ok = SyncClassification::classOf('zz_sc_module_table') === SyncClass::Content;
    SyncClassification::reset();
    return $ok && SyncClassification::classOf('zz_sc_module_table') === null;
});

check('environment con syncSchema() → incoerente (finirebbe in git); code con syncSchema() va bene', function () {
    $bad = SyncClassification::report([EnvironmentWithSync::class]);
    $good = SyncClassification::report([CodeWithSync::class]);
    return $bad['inconsistent'] === ['zz_sc_env_sync'] && $good['inconsistent'] === [];
});

// ---- come un sito: Model in un ROOT temporaneo --------------------------------

$base = sys_get_temp_dir().'/sync-classification-'.getmypid();
$cleanup = static function (string $dir) use (&$cleanup): void {
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            is_dir($dir.'/'.$entry) ? $cleanup($dir.'/'.$entry) : unlink($dir.'/'.$entry);
        }
    }
    rmdir($dir);
};
register_shutdown_function(static fn () => is_dir($base) && $cleanup($base));

$siteModel = static fn (string $class, bool $classified): string => "<?php\nnamespace Zz\\Site;\nuse Wonder\\App\\Support\\SyncClass;\nuse Wonder\\App\\Support\\SyncSchema;\nfinal class {$class} extends \\Wonder\\App\\Model\n{\n    public static string \$table = 'zz_sc_site_".strtolower($class)."';\n"
    .($classified ? "    public static function syncClass(): ?SyncClass { return SyncSchema::content(); }\n" : '')
    ."    public static function tableSchema(): array { return []; }\n    public static function dataSchema(): array { return []; }\n}\n";

$siteRoot = static function (string $name, string $class, bool $classified) use ($base, $siteModel): void {
    @mkdir($base.'/'.$name.'/custom/class/Models', 0777, true);
    file_put_contents($base.'/'.$name.'/custom/class/Models/'.$class.'.php', $siteModel($class, $classified));
    $GLOBALS['ROOT'] = $base.'/'.$name;
    ModelRegistry::reset();
    SyncClassification::reset();
};

check('sito: un Model nuovo senza classe fa fallire il controllo', function () use ($siteRoot) {
    $siteRoot('site1', 'Orders', false);

    try {
        SyncClassification::assertComplete();
    } catch (\RuntimeException $e) {
        return str_contains($e->getMessage(), 'zz_sc_site_orders');
    }

    return false;
});

check('sito: lo stesso Model con la classe fa passare il controllo (e il framework resta a posto)', function () use ($siteRoot) {
    $siteRoot('site2', 'Invoices', true);
    SyncClassification::assertComplete();

    return SyncClassification::classOf('zz_sc_site_invoices') === SyncClass::Content;
});

// Ripristina ROOT e registri per chi include questo file.
if ($previousRoot === null) { unset($GLOBALS['ROOT']); } else { $GLOBALS['ROOT'] = $previousRoot; }
ModelRegistry::reset();
SyncClassification::reset();

summary();
