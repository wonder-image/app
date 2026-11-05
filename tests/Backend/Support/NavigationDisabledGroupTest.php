<?php
/** php tests/Backend/Support/NavigationDisabledGroupTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Backend\Support\BackendNavigation;

// Chi dichiara il gruppo è spento (come «Tracciati file» col catalogo spento).
final class NavGroupOwnerOff extends Resource
{
    public static string $path = 'nav-test/owner';
    public static function titleLabel(): string { return 'Proprietario'; }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('nav-test', 'Prova', 'bi-gear', 900, ['admin'])
            ->group('files', 'File', 50)
            ->title('Proprietario')
            ->enabled(false);
    }
}

final class NavGroupMember extends Resource
{
    public static string $path = 'nav-test/member';
    public static function titleLabel(): string { return 'Membro'; }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('nav-test')
            ->inGroup('files')
            ->title('Membro');
    }
}

final class NavGroupOnlyOff extends Resource
{
    public static string $path = 'nav-test/solo';
    public static function titleLabel(): string { return 'Solo'; }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('nav-test')
            ->group('vuoto', 'Vuoto', 60)
            ->title('Solo')
            ->enabled(false);
    }
}

function navTestBuild(array $classes): array
{
    $schemas = [];
    foreach ($classes as $class) {
        $schemas[$class] = $class::navigationSchema();
    }
    $build = new ReflectionMethod(BackendNavigation::class, 'buildSections');

    return $build->invoke(null, $schemas);
}

function navTestTitles(array $nodes): array
{
    $out = [];
    foreach ($nodes as $node) {
        $out[] = (string) ($node['title'] ?? '');
        $out = array_merge($out, navTestTitles((array) ($node['subnavs'] ?? [])));
    }

    return $out;
}

check('un gruppo dichiarato da una Resource spenta resta valido per le altre voci', function () {
    $titles = navTestTitles(navTestBuild([NavGroupOwnerOff::class, NavGroupMember::class]));

    return in_array('File', $titles, true)
        && in_array('Membro', $titles, true)
        && !in_array('Proprietario', $titles, true);
});

check('un gruppo senza voci accese non compare', function () {
    $titles = navTestTitles(navTestBuild([NavGroupOwnerOff::class, NavGroupMember::class, NavGroupOnlyOff::class]));

    return !in_array('Vuoto', $titles, true) && !in_array('Solo', $titles, true);
});

summary();
