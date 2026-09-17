<?php
/** php tests/App/ResourceReadonlyTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Environment;
use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\Support\SyncSchema;

final class ReadonlyTestLocalOnlyModel extends Model
{
    public static string $table = 'readonly_test_local_only';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds()->localOnly();
    }

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class ReadonlyTestSyncedModel extends Model
{
    public static string $table = 'readonly_test_synced';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow();
    }

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class ReadonlyTestLocalOnlyResource extends Resource
{
    public static string $model = ReadonlyTestLocalOnlyModel::class;
}

final class ReadonlyTestSyncedResource extends Resource
{
    public static string $model = ReadonlyTestSyncedModel::class;
}

final class ReadonlyTestNavigationResource extends NavigationOnlyResource
{
    public static function path(): string
    {
        return 'readonly-test-navigation';
    }
}

function withEnvironment(?string $value): void {
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');

    if ($value !== null) {
        $_ENV['APP_ENV'] = $value;
    }

    Environment::reset();
}

check('localOnly in produzione: sola lettura', function () {
    withEnvironment('production');
    return ReadonlyTestLocalOnlyResource::isReadonly() === true;
});

check('localOnly senza APP_ENV: sola lettura', function () {
    withEnvironment(null);
    return ReadonlyTestLocalOnlyResource::isReadonly() === true;
});

check('localOnly in locale: modificabile', function () {
    withEnvironment('local');
    return ReadonlyTestLocalOnlyResource::isReadonly() === false;
});

check('tabella sincronizzata senza localOnly: sempre modificabile', function () {
    withEnvironment('production');
    return ReadonlyTestSyncedResource::isReadonly() === false;
});

check('Resource senza Model (navigation-only): modificabile, nessuna eccezione', function () {
    withEnvironment('production');
    return ReadonlyTestNavigationResource::isReadonly() === false;
});

check('avviso di sola lettura', function () {
    return ReadonlyTestLocalOnlyResource::readonlyNotice() === 'Si modifica in locale e si pubblica con il deploy.';
});

summary();
