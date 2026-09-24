<?php
/** php tests/App/ResourceSchema/InputMaxLengthTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.2.0');

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs;
use Wonder\Elements\Form\Components\InputText as TextElement;

check('maxLength() nello schema dell\'input', function () {
    return FormField::key('short')->text()->maxLength(255)->get('max_length') === 255;
});

check('maxLength() arriva all\'Element (schema max-length)', function () {
    $element = FormField::key('short')->text()->maxLength(255)->compile();

    return $element instanceof TextElement && $element->getSchema('max-length') === 255;
});

check('maxLength() c\'è anche sul tipo diretto', function () {
    return Inputs\InputText::key('short')->maxLength(80)->compile()->getSchema('max-length') === 80;
});

check('senza maxLength() l\'Element non ha limite', function () {
    return FormField::key('short')->text()->compile()->getSchema('max-length') === null;
});

check('maxLength() negativo o zero non mette limiti', function () {
    return FormField::key('short')->text()->maxLength(0)->compile()->getSchema('max-length') === null;
});

foreach (['wonder', 'bootstrap'] as $theme) {
    check("maxlength nel markup [{$theme}]", function () use ($theme) {
        return str_contains(FormField::key('short')->text()->maxLength(255)->render($theme), 'maxlength="255"');
    });

    check("senza maxLength() niente attributo [{$theme}]", function () use ($theme) {
        return !str_contains(FormField::key('short')->text()->render($theme), 'maxlength');
    });

    check("Element renderizzato direttamente [{$theme}]", function () use ($theme) {
        \Wonder\App\Theme::set($theme);

        return str_contains((new TextElement('short'))->maxLength(40)->render(), 'maxlength="40"');
    });

    check("un maxlength già negli attributi non si duplica [{$theme}]", function () use ($theme) {
        $html = FormField::key('short')->text()->maxLength(255)->attribute('maxlength="10"')->render($theme);

        return substr_count($html, 'maxlength=') === 1;
    });
}

summary();
