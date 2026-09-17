<?php
/** php tests/App/Support/DefaultRowsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Module\Manifest;
use Wonder\App\Module\ManifestValidator;
use Wonder\App\Support\DefaultRows;

final class TestModuleDefaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
    }
}

final class TestNotModuleDefaults
{
}

function defaultsManifest(?string $defaults): Manifest
{
    $data = ['slug' => 'prova', 'database' => ['models' => 'src/Models']];

    if ($defaults !== null) {
        $data['database']['defaults'] = $defaults;
    }

    return Manifest::fromArray('/tmp/prova', '/tmp/prova/module.json', $data, 'test');
}

check('missingRows: solo le chiavi che non esistono', function () {
    $rows = [
        ['code' => 'bank-transfer', 'name' => 'Bonifico'],
        ['code' => 'cash', 'name' => 'Contanti'],
    ];
    return DefaultRows::missingRows($rows, 'code', ['bank-transfer']) === [['code' => 'cash', 'name' => 'Contanti']];
});

check('missingRows: chiavi esistenti confrontate come stringhe', function () {
    return DefaultRows::missingRows([['code' => '10'], ['code' => '22']], 'code', [10]) === [['code' => '22']];
});

check('missingRows: duplicati nello stesso elenco inseriti una volta', function () {
    return DefaultRows::missingRows([['code' => 'a'], ['code' => 'a']], 'code', []) === [['code' => 'a']];
});

check('missingRows: riga senza chiave rifiutata', function () {
    try {
        DefaultRows::missingRows([['name' => 'senza codice']], 'code', []);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'code');
    }
});

check('total() parte da zero', function () {
    return (new DefaultRows())->total() === 0;
});

check('Manifest::defaultsClass()', function () {
    return defaultsManifest(TestModuleDefaults::class)->defaultsClass() === TestModuleDefaults::class
        && defaultsManifest(null)->defaultsClass() === null
        && defaultsManifest('  ')->defaultsClass() === null;
});

check('validator: classe defaults valida senza errori dedicati', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest(TestModuleDefaults::class)));
    return !str_contains($errors, 'database.defaults') && !str_contains($errors, 'ModuleDefaults');
});

check('validator: classe inesistente', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest('Nope\\Missing')));
    return str_contains($errors, 'Classe database.defaults non autoloadabile: Nope\\Missing');
});

check('validator: classe che non implementa il contratto', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest(TestNotModuleDefaults::class)));
    return str_contains($errors, 'TestNotModuleDefaults deve implementare');
});

summary();
