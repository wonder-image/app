<?php
/** php tests/App/ResourceDeleteGuardTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;

final class DeleteGuardModel extends Model
{
    public static string $table = 'delete_guard_rows';

    public static function tableSchema(): array
    {
        return [];
    }

    public static function dataSchema(): array
    {
        return [];
    }
}

final class FreeDeleteResource extends Resource
{
    public static string $model = DeleteGuardModel::class;
}

final class GuardedDeleteResource extends Resource
{
    public static string $model = DeleteGuardModel::class;

    public static function assertDeletable(int|string $id): void
    {
        throw new RuntimeException("La riga {$id} non si può eliminare.");
    }
}

check('di default ogni record è eliminabile', function () {
    FreeDeleteResource::assertDeletable(1);

    return true;
});

check('deleteRecord() si ferma prima del database se la Resource vieta', function () {
    try {
        GuardedDeleteResource::deleteRecord(7);
    } catch (RuntimeException $exception) {
        return $exception->getMessage() === 'La riga 7 non si può eliminare.';
    }

    return false;
});

summary();
