<?php
/** php tests/Themes/CheckValueMatchTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;

/**
 * Le spunte salvate si vedono riaprendo la scheda.
 *
 * PHP rende intere le chiavi numeriche delle opzioni (`'12'` diventa `12`),
 * mentre i valori salvati arrivano quasi sempre stringa: un confronto stretto
 * fra i due non trova mai niente, e salvando senza toccare le spunte si
 * perdevano.
 */
$opzioni = ['12' => 'Magliette', '13' => 'Felpe'];

check("l'albero spunta i valori stringa su chiavi numeriche", function () use ($opzioni) {
    $html = FormField::key('categories')->checkTree($opzioni)->value(['12'])->render('bootstrap');

    return substr_count($html, ' checked') === 1
        && str_contains($html, '<li id="12" data-jstree=\'{"selected": true }\'>');
});

check("l'albero spunta anche i valori interi", function () use ($opzioni) {
    $html = FormField::key('categories')->checkTree($opzioni)->value([13])->render('bootstrap');

    return substr_count($html, ' checked') === 1
        && str_contains($html, '<li id="13" data-jstree');
});

check('il gruppo di spunte fa lo stesso', function () use ($opzioni) {
    $html = FormField::key('sizes')->checkbox()->options($opzioni)->value(['13'])->render('bootstrap');

    return substr_count($html, ' checked') === 1
        && (bool) preg_match('/value="13"[^>]*checked/', $html);
});

check('anche a pillole', function () use ($opzioni) {
    $html = FormField::key('sizes')->checkbox()->options($opzioni)->pills()->value(['12', '13'])->render('bootstrap');

    return substr_count($html, ' checked') === 2;
});

/*
 * La stella dell'albero: il campo che tiene la voce principale fra quelle
 * spuntate. L'albero dice come si chiama, la lib lo tiene aggiornato.
 */
check("l'albero dichiara il campo della voce principale", function () use ($opzioni) {
    $html = FormField::key('categories')->checkTree($opzioni)->primaryField('main_category')->render('bootstrap');

    return str_contains($html, 'data-wi-tree="checkbox" data-wi-tree-primary="main_category"');
});

check('senza, niente attributo', function () use ($opzioni) {
    $html = FormField::key('categories')->checkTree($opzioni)->render('bootstrap');

    return !str_contains($html, 'data-wi-tree-primary');
});

summary();
