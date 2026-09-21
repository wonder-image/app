<?php
/** php tests/Backend/Support/RedirectAfterStoreTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\Backend\Support\ResourcePagePresenter;

/**
 * Gli indirizzi delle Resource nascono da rotte con un nome. Qui il framework
 * non è avviato, quindi la rotta la finge questa funzione: al test interessa
 * *quale* indirizzo viene scelto, non come è scritto.
 */
if (!function_exists('__r')) {
    function __r(string $name, array $parameters = []): string
    {
        $id = isset($parameters['id']) ? '/'.$parameters['id'] : '';

        return '/'.$name.$id;
    }
}

final class RedirectTestModel extends Model
{
    public static string $table = 'redirect_test';

    public static function tableSchema(): array
    {
        return [];
    }

    public static function dataSchema(): array
    {
        return [];
    }
}

final class RedirectTestResource extends Resource
{
    public static string $model = RedirectTestModel::class;
    private static string $target = 'list';

    public static function redirectTo(string $target): void
    {
        self::$target = $target;
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->redirect('store', self::$target);
    }
}

$presenter = new ResourcePagePresenter(RedirectTestResource::class);
$lista = '/backend.resource.'.RedirectTestResource::slug().'.list';

check('di suo un salvataggio torna all\'elenco', function () use ($presenter, $lista) {
    RedirectTestResource::redirectTo('list');

    return $presenter->redirectUrl('store', 7) === $lista;
});

check('chiedendo la scheda si atterra sulla riga appena creata', function () use ($presenter) {
    RedirectTestResource::redirectTo('edit');

    return $presenter->redirectUrl('store', 7)
        === '/backend.resource.'.RedirectTestResource::slug().'.update/7';
});

check('senza id non c\'è scheda: si torna all\'elenco', function () use ($presenter, $lista) {
    RedirectTestResource::redirectTo('edit');

    return $presenter->redirectUrl('store') === $lista
        && $presenter->redirectUrl('store', 0) === $lista;
});

check('il form vuoto resta una destinazione possibile', function () use ($presenter) {
    RedirectTestResource::redirectTo('create');

    return $presenter->redirectUrl('store', 7)
        === '/backend.resource.'.RedirectTestResource::slug().'.create';
});

summary();
