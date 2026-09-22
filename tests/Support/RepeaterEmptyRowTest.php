<?php
/** php tests/Support/RepeaterEmptyRowTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Support\Repeater;

$righe = static fn (array $post): array => Repeater::rowsFromRequest('images', $post);

check('una riga con tutti i campi vuoti non si salva', function () use ($righe) {
    return $righe(['images' => ['row_1' => ['id' => '', 'alt' => '', 'status' => '']]]) === [];
});

check('nemmeno se un campo posta un array di vuoti', function () use ($righe) {
    // È il caso del campo file in una riga nuova: posta `['']`.
    return $righe(['images' => ['row_1' => ['id' => '', 'file' => [''], 'alt' => '']]]) === [];
});

check('una riga con qualcosa dentro si salva', function () use ($righe) {
    $rows = $righe(['images' => ['row_1' => ['id' => '', 'file' => [''], 'alt' => 'Maglietta blu']]]);

    return count($rows) === 1 && $rows[0]['alt'] === 'Maglietta blu';
});

check('un file vero tiene la riga', function () use ($righe) {
    $rows = $righe(['images' => ['row_1' => ['id' => '', 'file' => ['foto.jpg'], 'alt' => '']]]);

    return count($rows) === 1;
});

check('la busta di un file mai scelto non tiene in piedi la riga', function () use ($righe) {
    // È quello che PHP mette in $_FILES quando il campo è vuoto.
    $busta = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0];

    return $righe(['images' => ['row_1' => ['id' => '', 'alt' => '', 'file' => $busta]]]) === [];
});

check('una busta con un file dentro sì', function () use ($righe) {
    $busta = ['name' => 'foto.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 120];

    return count($righe(['images' => ['row_1' => ['id' => '', 'alt' => '', 'file' => $busta]]])) === 1;
});

check("l'elenco vuoto del campo file non tiene in piedi la riga", function () use ($righe) {
    // Il widget posta sempre `file__wi_files`: `[]` quando non c'è niente.
    return $righe(['images' => ['row_1' => [
        'id' => '',
        'alt' => '',
        'status' => '',
        'file__wi_files' => '[]',
    ]]]) === [];
});

check('con un file già caricato la riga resta', function () use ($righe) {
    $rows = $righe(['images' => ['row_1' => [
        'id' => '12',
        'alt' => '',
        'file__wi_files' => '["foto.jpg"]',
    ]]]);

    return count($rows) === 1;
});

summary();
