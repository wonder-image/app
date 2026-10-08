<?php
/**
 * Catalog e ComponentDoc: slug, categorie, gruppi, ordinamento, file che
 * non ritornano una scheda e slug duplicati.
 *
 *   php tests/Docs/CatalogTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Docs\Catalog;
use Wonder\Docs\Category;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Button;

echo "Catalog\n";

$tmp = sys_get_temp_dir().'/wi-docs-catalog-'.getmypid();
@mkdir($tmp.'/components', 0777, true);
@mkdir($tmp.'/extra', 0777, true);

register_shutdown_function(static function () use ($tmp): void {
    foreach (glob($tmp.'/*/*') ?: [] as $file) { @unlink($file); }
    foreach (glob($tmp.'/*') ?: [] as $dir) { @rmdir($dir); }
    @rmdir($tmp);
});

check('lo slug di default è il kebab-case del nome corto', function () {
    return ComponentDoc::for(\Wonder\Elements\Form\Components\InputText::class)->getSlug() === 'input-text'
        && ComponentDoc::for(\Wonder\Elements\Components\QuickCreateButton::class)->getSlug() === 'quick-create-button'
        && ComponentDoc::for(\Wonder\Elements\Form\Components\reCAPTCHA::class)->getSlug() === 're-captcha'
        && ComponentDoc::slugify('TextareaEditor') === 'textarea-editor';
});

check('for() rifiuta una classe che non esiste e slug() uno slug sporco', function () {
    try { ComponentDoc::for('Wonder\\Nope'); return false; } catch (InvalidArgumentException) {}
    try { ComponentDoc::for(Button::class)->slug('Non Valido'); return false; } catch (InvalidArgumentException) {}

    return true;
});

check('example() accetta la forma breve e un Example configurato', function () {
    $doc = ComponentDoc::for(Button::class)
        ->example('Base', "Button::make('A')", 'descrizione')
        ->example(Example::make('Solo Bootstrap')->code("Button::make('B')")->themes('bootstrap')->height(120));
    $examples = $doc->getExamples();

    return count($examples) === 2
        && $examples[0]->getTitle() === 'Base' && $examples[0]->getDescription() === 'descrizione'
        && $examples[1]->supports('bootstrap') && !$examples[1]->supports('wonder') && $examples[1]->getHeight() === 120
        && $examples[0]->supports('wonder');
});

check('uses() contiene sempre la classe del componente, senza doppioni', function () {
    $doc = ComponentDoc::for(Button::class)->uses(Button::class, '\\Wonder\\Elements\\Components\\Badge', 'Wonder\\Elements\\Components\\Badge');

    return $doc->getUses() === [Button::class, 'Wonder\\Elements\\Components\\Badge'];
});

check('le categorie di default sono form, components, media e charts, in quest\'ordine', function () {
    Catalog::reset();

    return array_keys(Catalog::categories()) === ['form', 'components', 'media', 'charts']
        && Catalog::category('form')?->groupTitle('text') === 'Testo e numeri'
        && Catalog::category('form')?->groupOrder('text') === 1
        && Catalog::category('media')?->groups() === [];
});

check('il catalogo del pacchetto si carica e contiene le schede di base', function () {
    Catalog::reset();
    $docs = Catalog::all();

    return isset($docs['button'], $docs['alert'], $docs['input-text'])
        && $docs['button']->getCategory() === 'components'
        && str_ends_with((string) $docs['button']->getFile(), 'docs/components/components/button.php');
});

check('le schede sono ordinate per categoria, gruppo e ordine', function () {
    Catalog::reset();
    $slugs = array_keys(Catalog::all());

    return array_search('input-text', $slugs, true) < array_search('button', $slugs, true)
        && array_search('button', $slugs, true) < array_search('alert', $slugs, true);
});

check('grouped() mette le schede sotto categoria e gruppo e neighbors() segue l\'ordine', function () {
    Catalog::reset();
    $grouped = Catalog::grouped();
    $neighbors = Catalog::neighbors(Catalog::find('button'));

    return isset($grouped['components']['action'])
        && $grouped['components']['action'][0]->getSlug() === 'button'
        && $neighbors['previous'] !== null && $neighbors['next'] !== null;
});

check('addPath() aggiunge le schede di un\'altra cartella, con la categoria dalla sottocartella', function () use ($tmp) {
    file_put_contents($tmp.'/components/mio.php', '<?php return \\Wonder\\Docs\\ComponentDoc::for(\\Wonder\\Elements\\Components\\Badge::class)->slug("mio-badge")->description("x")->example("Base", "Badge::make(\'a\')");');
    Catalog::reset();
    Catalog::addPath($tmp);
    $doc = Catalog::find('mio-badge');
    $ok = $doc !== null && $doc->getCategory() === 'components';
    unlink($tmp.'/components/mio.php');
    Catalog::reset();

    return $ok;
});

check('un file che non ritorna una scheda ferma il caricamento nominando il file', function () use ($tmp) {
    file_put_contents($tmp.'/components/rotto.php', '<?php return 42;');
    Catalog::reset();
    Catalog::addPath($tmp);

    try {
        Catalog::all();
        $ok = false;
    } catch (RuntimeException $exception) {
        $ok = str_contains($exception->getMessage(), 'rotto.php');
    }

    unlink($tmp.'/components/rotto.php');
    Catalog::reset();

    return $ok;
});

check('uno slug duplicato ferma il caricamento nominando i due file', function () use ($tmp) {
    file_put_contents($tmp.'/components/doppione.php', '<?php return \\Wonder\\Docs\\ComponentDoc::for(\\Wonder\\Elements\\Components\\Button::class);');
    Catalog::reset();
    Catalog::addPath($tmp);

    try {
        Catalog::all();
        $ok = false;
    } catch (RuntimeException $exception) {
        $ok = str_contains($exception->getMessage(), "'button'") && str_contains($exception->getMessage(), 'doppione.php');
    }

    unlink($tmp.'/components/doppione.php');
    Catalog::reset();

    return $ok;
});

check('una categoria sconosciuta ferma il caricamento', function () use ($tmp) {
    file_put_contents($tmp.'/extra/x.php', '<?php return \\Wonder\\Docs\\ComponentDoc::for(\\Wonder\\Elements\\Components\\Badge::class)->slug("x-badge");');
    Catalog::reset();
    Catalog::addPath($tmp);

    try {
        Catalog::all();
        $ok = false;
    } catch (RuntimeException $exception) {
        $ok = str_contains($exception->getMessage(), "'extra'");
    }

    unlink($tmp.'/extra/x.php');
    Catalog::reset();

    return $ok;
});

check('addCategory() registra una categoria nuova usabile dalle schede', function () {
    Catalog::reset();
    Catalog::addCategory(new Category('plugin', 'Plugin', '', 50));
    $ok = Catalog::category('plugin') !== null && array_key_last(Catalog::categories()) === 'plugin';
    Catalog::reset();

    return $ok;
});

summary();
