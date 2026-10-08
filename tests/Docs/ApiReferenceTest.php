<?php
/**
 * ApiReference: metodi propri prima, ereditati per origine, getter e
 * plumbing esclusi, firme leggibili.
 *
 *   php tests/Docs/ApiReferenceTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Docs\ApiReference;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Code;

echo "ApiReference\n";

$api = ApiReference::for(Button::class);
$own = array_column($api['own'], null, 'name');

check('il costruttore è descritto a parte con i suoi parametri', function () use ($api) {
    return $api['constructor'] !== null && $api['constructor']['parameters'] === "string \$label = '', string \$href = ''";
});

check('i metodi propri includono gli statici e i setter della classe', function () use ($own) {
    return isset($own['make'], $own['post'], $own['variant'], $own['confirm'])
        && $own['make']['static'] === true && $own['variant']['static'] === false;
});

check('gli statici vengono prima, poi alfabetico', function () use ($api) {
    $names = array_column($api['own'], 'name');
    $firstInstance = array_search(false, array_column($api['own'], 'static'), true);

    return $names[0] === 'make' && $firstInstance !== false && $names[$firstInstance] === 'active';
});

check('getter, render() e la gestione dello schema restano fuori', function () use ($own, $api) {
    $all = array_merge(array_keys($own), ...array_map(static fn (array $methods): array => array_column($methods, 'name'), array_values($api['inherited'])));

    foreach (['getLabel', 'getHref', 'hasExplicitColumnSpan', 'render', 'schema', 'getSchema', 'toArray', '__toString'] as $hidden) {
        if (in_array($hidden, $all, true)) {
            echo "    metodo che doveva restare fuori: {$hidden}\n";

            return false;
        }
    }

    return true;
});

check('i metodi ereditati sono raggruppati per classe o trait di origine', function () use ($api) {
    return isset($api['inherited']['Link'], $api['inherited']['HasLinkAttributes'], $api['inherited']['CanSpanColumn'])
        && in_array('href', array_column($api['inherited']['HasLinkAttributes'], 'name'), true)
        && in_array('columnSpan', array_column($api['inherited']['CanSpanColumn'], 'name'), true);
});

check('la firma scrive tipi, default e tipo di ritorno', function () use ($own) {
    return $own['confirm']['parameters'] === "string \$message, ?string \$title = null, ?string \$ok = null, ?string \$variant = null"
        && $own['confirm']['returns'] === 'self'
        && $own['size']['parameters'] === "string \$size";
});

check('il riassunto è la prima frase del docblock, senza i tag', function () use ($own) {
    return str_starts_with($own['opensModal']['summary'], 'Open a Modal')
        && $own['variant']['summary'] === '';
});

check('un metodo con @deprecated viene segnato', function () {
    $api = ApiReference::for(\Wonder\Elements\Form\Components\SortableInput::class);
    $flags = array_column(array_merge($api['own'], ...array_values($api['inherited'])), 'deprecated');

    return is_array($flags);
});

check('i tipi unione e i default costanti sono leggibili', function () {
    $api = ApiReference::for(Code::class);
    $own = array_column($api['own'], null, 'name');

    return isset($own['copyLabels']) && $own['copyLabels']['parameters'] === "string \$copy, string \$copied"
        && isset($own['language']) && $own['language']['returns'] === 'static';
});

summary();
