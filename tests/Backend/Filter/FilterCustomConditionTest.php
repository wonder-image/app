<?php
/** php tests/Backend/Filter/FilterCustomConditionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../Sql/SqlLikeEmulator.php';

use Wonder\Backend\Filter\FilterCustom;

check('select: valore singolo', function () {
    return FilterCustom::condition(['column' => 'brand_id', 'input' => 'select'], '5') === "`brand_id` = '5' ";
});

check('select: apici escapati', function () {
    return FilterCustom::condition(['column' => 'brand_id', 'input' => 'select'], "x' OR '1'='1")
        === "`brand_id` = 'x\\' OR \\'1\\'=\\'1' ";
});

check('checkbox: l\'input nascosto vuoto non conta', function () {
    return FilterCustom::condition(['column' => 'status', 'input' => 'checkbox'], ['', 'a', 'b'])
        === "`status` IN ('a', 'b') ";
});

check('checkbox senza spunte: nessuna condizione', function () {
    return FilterCustom::condition(['column' => 'status', 'input' => 'checkbox'], ['']) === '';
});

check('tree: valori ripetuti una volta sola', function () {
    return FilterCustom::condition(['column' => 'category_id', 'input' => 'tree'], ['', '3', '3', '4'])
        === "`category_id` IN ('3', '4') ";
});

check('multiple: le LIKE stanno fra parentesi', function () {
    return FilterCustom::condition(['column' => 'tags', 'input' => 'checkbox', 'column_type' => 'multiple'], ['', 'x', 'y'])
        === "(`tags` LIKE '%\"x\"%' OR `tags` LIKE '%\"y\"%') ";
});

check('multiple: % e _ escapati', function () {
    return FilterCustom::condition(['column' => 'tags', 'input' => 'radio', 'column_type' => 'multiple'], 'a_b%')
        === "(`tags` LIKE '%\"a\\\\_b\\\\%\"%') ";
});

check('un array su un filtro a scelta singola non filtra', function () {
    return FilterCustom::condition(['column' => 'brand_id', 'input' => 'select'], ['5', '6']) === '';
});

check('radio: valore singolo', function () {
    return FilterCustom::condition(['column' => 'status', 'input' => 'radio'], 'draft') === "`status` = 'draft' ";
});

check('backtick nel nome della colonna escapato', function () {
    return FilterCustom::condition(['column' => 'a`b', 'input' => 'select'], '1') === "`a``b` = '1' ";
});

check('true, null e stringa vuota non filtrano', function () {
    $options = ['column' => 'x', 'input' => 'select'];

    return FilterCustom::condition($options, true) === ''
        && FilterCustom::condition($options, null) === ''
        && FilterCustom::condition($options, '') === '';
});

check('0 filtra, come stringa e come numero', function () {
    $options = ['column' => 'x', 'input' => 'select'];

    return FilterCustom::condition($options, '0') === "`x` = '0' "
        && FilterCustom::condition($options, 0) === "`x` = '0' ";
});

// Escape di una connessione con NO_BACKSLASH_ESCAPES: l'apice si raddoppia, la barra resta.
$noBackslash = static fn (string $v): string => str_replace("'", "''", $v);

check('escape passato: select, checkbox e multiple lo usano al posto di addslashes', function () use ($noBackslash) {
    return FilterCustom::condition(['column' => 'brand_id', 'input' => 'select'], "x' OR '1'='1", $noBackslash)
            === "`brand_id` = 'x'' OR ''1''=''1' "
        && FilterCustom::condition(['column' => 'status', 'input' => 'checkbox'], ['', "a'b", 'c'], $noBackslash)
            === "`status` IN ('a''b', 'c') "
        && FilterCustom::condition(['column' => 'tags', 'input' => 'radio', 'column_type' => 'multiple'], "a'_%", $noBackslash)
            === "(`tags` LIKE '%\"a''\\_\\%\"%') ";
});

check('escape passato: valori normali identici a quelli di addslashes', function () use ($noBackslash) {
    $casi = [
        [['column' => 'brand_id', 'input' => 'select'], '5'],
        [['column' => 'status', 'input' => 'checkbox'], ['', 'a', 'b']],
        [['column' => 'tags', 'input' => 'checkbox', 'column_type' => 'multiple'], ['', 'x', 'y']],
        [['column' => 'x', 'input' => 'select'], 0],
    ];

    foreach ($casi as [$options, $value]) {
        if (FilterCustom::condition($options, $value, $noBackslash) !== FilterCustom::condition($options, $value)) {
            return false;
        }
    }

    return true;
});

// Escape della connessione in sql_mode standard, come mysqli::real_escape_string.
$standard = static fn (string $v): string => addcslashes($v, "\\\0\n\r'\"\x1a");

check('multiple: una barra nel valore non riattiva % e _ come jolly', function () use ($standard) {
    $options = ['column' => 'tags', 'input' => 'radio', 'column_type' => 'multiple'];
    $casi = [
        // valore => [riga che deve corrispondere, riga che non deve]
        '\\%' => ['["\\%"]', '["\\abc"]'],
        '\\_' => ['["\\_"]', '["\\x"]'],
        '\\\\' => ['["\\\\"]', '["\\"]'],
        "\\'" => ['["\\\'"]', '["\\x"]'],
    ];

    foreach ($casi as $valore => [$si, $no]) {
        foreach ([$standard, null] as $escape) {
            $sql = FilterCustom::condition($options, (string) $valore, $escape);
            if (!sqlLikeMatches($sql, $si) || sqlLikeMatches($sql, $no)) {
                echo "    {$valore}: {$sql}\n";
                return false;
            }
        }
    }

    return true;
});

check('input nascosti escapati', function () {
    return FilterCustom::hiddenInputs(['redirect' => '"><script>'])
        === '<input type="hidden" name="redirect" value="&quot;&gt;&lt;script&gt;">';
});

check('input nascosti: gli array restano fuori', function () {
    return FilterCustom::hiddenInputs(['id' => '7', 'x' => ['a']]) === '<input type="hidden" name="id" value="7">';
});

summary();
