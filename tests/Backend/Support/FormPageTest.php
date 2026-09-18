<?php
/** php tests/Backend/Support/FormPageTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Resource;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;

final class PaginaFormDiProva extends NavigationOnlyResource
{
    public static array $salvati = [];

    public static function path(): string { return 'prova/pagina-form'; }
    public static function icon(): string { return 'bi-toggles'; }
    public static function titleLabel(): string { return 'Pagina di prova'; }
    public static function isFormPage(): bool { return true; }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->inSection('set-up')->authority(['admin']);
    }

    public static function formSchema(): array
    {
        return [FormField::key('orders')->toggle()->label('Ordini')->description('Gestione degli ordini.')];
    }

    public static function formPageValues(): array
    {
        return ['orders' => 'true'];
    }

    public static function submitFormPage(array $values): string
    {
        static::$salvati = $values;

        return 'Salvato: '.implode(', ', array_keys($values)).'.';
    }
}

check('di default una Resource non è una pagina-form', fn () =>
    Resource::isFormPage() === false
    && Resource::formPageValues() === []
    && Resource::submitFormPage(['a' => 'b']) === ''
);

check('la pagina-form dichiara valori e salvataggio', fn () =>
    PaginaFormDiProva::isFormPage() === true
    && PaginaFormDiProva::formPageValues() === ['orders' => 'true']
    && PaginaFormDiProva::submitFormPage(['orders' => 'true']) === 'Salvato: orders.'
);

check('il salvataggio riceve i valori inviati', function () {
    PaginaFormDiProva::submitFormPage(['orders' => 'false', 'returns' => 'true']);

    return PaginaFormDiProva::$salvati === ['orders' => 'false', 'returns' => 'true'];
});

summary();
