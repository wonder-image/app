<?php
/** php tests/Themes/CheckTreeValuesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;

/**
 * Le caselle che il form posta stanno fuori dall'albero.
 *
 * jstree tiene l'HTML del nodo come testo: chiudendo un genitore toglie dal
 * DOM i nodi figli, e ridisegnando un nodo rimette le caselle com'erano
 * arrivate dal server. Con le caselle nei nodi, una spunta sotto un ramo
 * chiuso spariva dal salvataggio. La lib le tiene allineate a jstree.
 */
$opzioni = [
    '1' => ['name' => 'Abbigliamento', 'child' => [
        '2' => 'Magliette',
        '3' => ['name' => 'Felpe', 'child' => ['4' => 'Con cappuccio']],
    ]],
    '5' => ['name' => 'Scarpe <b>', 'filter' => ['gruppo' => 'x']],
];

function albero(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/** @return list<string> */
function valori(DOMXPath $xpath, string $query): array
{
    $valori = [];

    foreach ($xpath->query($query) as $nodo) {
        $valori[] = $nodo->getAttribute('value');
    }

    return $valori;
}

$spunte = FormField::key('categories')->checkTree($opzioni, true)->value(['2', '4'])->required()->render('bootstrap');
$scelta = FormField::key('parent_id')->checkTree($opzioni, false, 'radio')->value('3')->render('bootstrap');

check("i nodi dell'albero portano solo l'etichetta", function () use ($spunte) {
    $xpath = albero($spunte);

    return $xpath->query('//*[@data-wi-tree]//input')->length === 0
        && $xpath->query('//*[@data-wi-tree]//li')->length === 5
        && str_contains($spunte, '<li id="5">Scarpe &lt;b&gt;</li>');
});

check('una casella per voce, figli compresi, fuori dall\'albero', function () use ($spunte) {
    $xpath = albero($spunte);
    $contenitore = $xpath->query('//*[@data-wi-tree-values]');

    return $contenitore->length === 1
        && $contenitore->item(0)->getAttribute('data-wi-tree-values') === 'categories[]'
        && valori($xpath, '//*[@data-wi-tree-values]/input[@type="checkbox"][@name="categories[]"]') === ['1', '2', '3', '4', '5'];
});

check('spuntate solo le voci scelte', function () use ($spunte) {
    return valori(albero($spunte), '//*[@data-wi-tree-values]/input[@checked]') === ['2', '4'];
});

check('le caselle restano nel contenitore del campo, per la validazione', function () use ($spunte) {
    $xpath = albero($spunte);

    return $xpath->query('//div[starts-with(@id, "container-")][contains(@class, "wi-checkbox-required")]//*[@data-wi-tree-values]/input')->length === 5
        && $xpath->query('//*[@data-wi-tree-values]/input[@required]')->length === 0
        && $xpath->query('//*[@data-wi-tree-values]/input[@data-wi-check="true"]')->length === 5;
});

check('il filtro di una voce resta sulla sua casella', function () use ($spunte) {
    return valori(albero($spunte), '//*[@data-wi-tree-values]/input[@data-gruppo="x"]') === ['5'];
});

check('il campo vuoto delle spunte resta, prima delle caselle', function () use ($spunte) {
    $vuoto = strpos($spunte, '<input type="hidden" name="categories[]">');
    $caselle = strpos($spunte, 'data-wi-tree-values=');

    return $vuoto !== false && $caselle !== false && $vuoto < $caselle;
});

check('a scelta singola il nome non ha le parentesi e niente campo vuoto', function () use ($scelta) {
    $xpath = albero($scelta);

    return $xpath->query('//*[@data-wi-tree-values="parent_id"]/input[@name="parent_id"]')->length === 5
        && $xpath->query('//input[@type="hidden"]')->length === 0
        && valori($xpath, '//*[@data-wi-tree-values]/input[@checked]') === ['3']
        && $xpath->query('//*[@data-wi-tree]//input')->length === 0;
});

check('una voce ripetuta nei rami ha una casella sola', function () {
    $html = FormField::key('categories')->checkTree([
        '1' => ['name' => 'Uomo', 'child' => ['9' => 'Saldi']],
        '2' => ['name' => 'Donna', 'child' => ['9' => 'Saldi']],
    ])->value(['9'])->render('bootstrap');

    return valori(albero($html), '//*[@data-wi-tree-values]/input') === ['1', '9', '2']
        && valori(albero($html), '//*[@data-wi-tree-values]/input[@checked]') === ['9'];
});

summary();
