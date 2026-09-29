<?php
/** php tests/App/ResourceSchema/TableLayoutDocsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\ResourceTableRenderer;

final class TableLayoutDocsModel extends Model
{
    public static string $table = 'table_layout_docs_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class TableLayoutDocsResource extends Resource
{
    public static string $model = TableLayoutDocsModel::class;
}

$layout = static fn (): TableLayoutSchema => TableLayoutSchema::for(TableLayoutDocsResource::class);

// Il bottone "Guida" resta acceso dove è sempre stato: nell'elenco che una
// Resource ha per sé.
check('la guida nell\'intestazione della tabella è accesa di suo', function () use ($layout) {
    return ($layout()->get('docs')['enabled'] ?? null) === true;
});

// Una tabella incorporata in un'altra pagina la guida ce l'ha già in testata:
// ripeterla dentro il riquadro sposta in basso il titolo e dice due volte la
// stessa cosa.
check('docs(false) spegne il bottone della guida', function () use ($layout) {
    return ($layout()->docs(false)->get('docs')['enabled'] ?? null) === false;
});

check('docs() lo riaccende', function () use ($layout) {
    return ($layout()->docs(false)->docs()->get('docs')['enabled'] ?? null) === true;
});

// «Intestazione pulita» vuol dire senza niente sopra la tabella: la guida
// faceva eccezione perché il renderer la aggiungeva dopo.
check('cleanHeader() toglie anche la guida', function () use ($layout) {
    return ($layout()->cleanHeader()->get('docs')['enabled'] ?? null) === false;
});

// La tabella incorporata in una scheda parte dal layout della sua Resource e
// ne cambia un pezzo: l'elenco vero della Resource conserva il suo bottone.
// La tabella vera vuole il database, quindi qui si guarda quello che il
// renderer ha in mano prima di montarla.
check('il renderer accetta un layout su misura', function () {
    $costruttore = (new ReflectionClass(ResourceTableRenderer::class))->getConstructor();
    $renderer = (new ReflectionClass(ResourceTableRenderer::class))->newInstanceWithoutConstructor();
    $costruttore->invoke(
        $renderer,
        TableLayoutDocsResource::class,
        [],
        TableLayoutDocsResource::tableLayoutSchema()->docs(false),
    );

    $letto = (new ReflectionProperty(ResourceTableRenderer::class, 'tableLayoutSchema'))->getValue($renderer);

    return ($letto['docs']['enabled'] ?? null) === false
        && (TableLayoutDocsResource::tableLayoutSchema()->get('docs')['enabled'] ?? null) === true;
});

summary();
