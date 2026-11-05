<?php
/** php tests/Themes/FormActionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

if (!function_exists('__t')) {
    function __t(string $key): string
    {
        return $key;
    }
}

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Form\Form;

// Il token CSRF esce solo con una sessione attiva.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_id('t');
@session_start();
$_SESSION = [];

$campo = static fn () => FormField::key('q')->text()->label('Cerca');

foreach (['bootstrap', 'wonder'] as $tema) {
    echo ucfirst($tema)."\n";

    check("$tema: senza action() il form resta action=\"\" POST multipart", function () use ($campo, $tema) {
        $html = (new Form)->components([$campo()])->render($tema);

        return str_contains($html, 'action=""') && str_contains($html, 'method="post"')
            && str_contains($html, 'enctype="multipart/form-data"');
    });

    check("$tema: action GET: niente enctype, niente CSRF, niente spinner", function () use ($campo, $tema) {
        $html = (new Form)->action('/x/?a=1&b=2', 'get')->components([$campo()])->render($tema);

        return str_contains($html, 'action="/x/?a=1&amp;b=2"') && str_contains($html, 'method="get"')
            && !str_contains($html, 'enctype') && !str_contains($html, '_csrf')
            && !str_contains($html, 'loadingSpinner');
    });

    check("$tema: action POST: multipart e campo CSRF", function () use ($campo, $tema) {
        $html = (new Form)->action('/x/')->components([$campo()])->render($tema);

        return str_contains($html, 'name="_csrf"') && str_contains($html, 'enctype="multipart/form-data"')
            && str_contains($html, 'action="/x/"');
    });

    check("$tema: metodo sconosciuto vale post", function () use ($campo, $tema) {
        $html = (new Form)->action('/x/', 'put')->components([$campo()])->render($tema);

        return str_contains($html, 'method="post"') && !str_contains($html, 'method="put"');
    });
}

echo "Modal\n";

check('un form dentro una Modal usa la sua action', function () use ($campo) {
    $html = Modal::make('M')->components([(new Form)->action('/dl/', 'get')->components([$campo()])])->render('bootstrap');

    return preg_match('/<form[^>]*action="\/dl\/"/', $html) === 1
        && preg_match('/<form[^>]*method="get"/i', $html) === 1
        && !str_contains($html, '_csrf');
});

check('un Form annidato senza action() resta com\'era: POST multipart, CSRF, colonna', function () use ($campo) {
    $html = ResourceFormLayoutRenderer::renderLayout((new Form)->components([(new Form)->components([$campo()])]));

    return str_contains($html, 'action=""') && str_contains($html, 'method="post"')
        && str_contains($html, 'enctype="multipart/form-data"') && str_contains($html, 'name="_csrf"')
        && preg_match('/<div class="col-12"[^>]*><form /', $html) === 1;
});

check('un Form annidato con action() POST in una Modal ha CSRF e multipart', function () use ($campo) {
    $html = Modal::make('M')->components([(new Form)->action('/up/', 'post')->components([$campo()])])->render('bootstrap');

    return preg_match('/<form[^>]*action="\/up\/"/', $html) === 1
        && str_contains($html, 'enctype="multipart/form-data"') && str_contains($html, 'name="_csrf"');
});

summary();
