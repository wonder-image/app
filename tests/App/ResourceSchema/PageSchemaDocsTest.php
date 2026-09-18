<?php
/** php tests/App/ResourceSchema/PageSchemaDocsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\Backend\Support\DocsAction;

final class DocsTestModel extends Model
{
    public static string $table = 'docs_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class DocsTestResource extends Resource
{
    public static string $model = DocsTestModel::class;
}

check('senza docs(): URL vuoto', function () {
    return PageSchema::for(DocsTestResource::class)->docsUrl('list') === '';
});

check('docs() senza pagine vale per list, create, edit e view', function () {
    $schema = PageSchema::for(DocsTestResource::class)->docs('https://guide.example.test/catalogo');
    foreach (['list', 'create', 'edit', 'view'] as $page) {
        if ($schema->docsUrl($page) !== 'https://guide.example.test/catalogo') {
            return false;
        }
    }
    return true;
});

check('docs() su pagine specifiche', function () {
    $schema = PageSchema::for(DocsTestResource::class)
        ->docs('/guida/elenco', 'list')
        ->docs('https://guide.example.test/scheda', ['edit', 'view']);
    return $schema->docsUrl('list') === '/guida/elenco'
        && $schema->docsUrl('edit') === 'https://guide.example.test/scheda'
        && $schema->docsUrl('create') === '';
});

check('URL non ammessi ignorati', function () {
    $schema = PageSchema::for(DocsTestResource::class)
        ->docs('javascript:alert(1)')
        ->docs('ftp://example.test/guida', 'list');
    return $schema->docsUrl('list') === '' && $schema->docsUrl('view') === '';
});

check('URL ammessi', function () {
    return PageSchema::isAllowedDocsUrl('https://guide.example.test/a')
        && PageSchema::isAllowedDocsUrl('http://guide.example.test/a')
        && PageSchema::isAllowedDocsUrl('/guida/a')
        && PageSchema::isAllowedDocsUrl('guida/a')
        && !PageSchema::isAllowedDocsUrl('//example.test/a')
        && !PageSchema::isAllowedDocsUrl('data:text/html,x')
        && !PageSchema::isAllowedDocsUrl('');
});

check('descriptor del pulsante', function () {
    return DocsAction::descriptor('https://guide.example.test/a', 'Guida') === [
        'label' => 'Guida',
        'href' => 'https://guide.example.test/a',
        'target' => '_blank',
        'class' => 'btn-info btn-sm',
        'icon' => 'bi bi-question-circle',
    ];
});

check('nell\'elenco il pulsante è lo stesso', function () {
    $html = (string) (new \Wonder\Elements\Components\Button('Guida', 'https://guide.example.test/a'))
        ->variant('info')
        ->size('sm')
        ->blank()
        ->render();

    return str_contains($html, 'btn-info')
        && str_contains($html, 'btn-sm')
        && !str_contains($html, 'btn-outline');
});

check('etichetta di ripiego senza traduzioni', function () {
    return DocsAction::label() === 'Guida';
});

summary();
