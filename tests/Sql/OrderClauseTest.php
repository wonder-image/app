<?php
/** php tests/Sql/OrderClauseTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Query;

function rifiutato(?string $order, ?string $direction): bool
{
    try {
        Query::orderClause($order, $direction);

        return false;
    } catch (RuntimeException $e) {
        return str_starts_with($e->getMessage(), 'Query: ');
    }
}

check('ordini dei chiamanti verificati: stesso SQL di prima', function () {
    $casi = [
        ['position', 'ASC', ' ORDER BY position ASC'],
        ['id', 'DESC', ' ORDER BY id DESC'],
        ['published_at DESC, id', 'DESC', ' ORDER BY published_at DESC, id DESC'],
        ['position, name', 'ASC', ' ORDER BY position, name ASC'],
        ['ordered_at DESC, id', 'DESC', ' ORDER BY ordered_at DESC, id DESC'],
        ['id ASC', null, ' ORDER BY id ASC'],
        ['`creation`', 'DESC', ' ORDER BY `creation` DESC'],
        [null, null, ''],
        ['', '', ''],
        ['name', null, ' ORDER BY name'],
    ];

    foreach ($casi as [$order, $direction, $atteso]) {
        if (Query::orderClause($order, $direction) !== $atteso) {
            return false;
        }
    }

    return true;
});

check('sottoquery del catalogo (prezzo e varianti) restano ammesse', function () {
    $prezzo = "(SELECT MIN(CASE WHEN p.`sale_price` > 0 AND p.`sale_price` < p.`price` THEN p.`sale_price` ELSE p.`price` END) FROM `gst_products` p WHERE p.`product_model_id` = `gst_product_models`.`id` AND p.`active` = 'true' AND p.`deleted` = 'false')";
    $varianti = "(SELECT m.`position` FROM `gst_product_models` m WHERE m.`id` = `gst_product_variants`.`product_model_id`), position, name";

    return Query::orderClause($prezzo, 'DESC') === " ORDER BY $prezzo DESC"
        && Query::orderClause($varianti, 'ASC') === " ORDER BY $varianti ASC";
});

check('direzione: asc e desc in minuscolo diventano maiuscole', function () {
    return Query::orderClause('id', 'desc') === ' ORDER BY id DESC'
        && Query::orderClause('id', ' Asc ') === ' ORDER BY id ASC';
});

check('direzione diversa da ASC/DESC rifiutata', function () {
    return rifiutato('id', 'DESC; DROP TABLE user')
        && rifiutato('id', 'DESC, (SELECT 1)')
        && rifiutato('id', 'SIDEWAYS')
        && rifiutato(null, 'DESC -- ');
});

check('punto e virgola e commenti fuori dalle stringhe rifiutati', function () {
    return rifiutato('id; DROP TABLE user', null)
        && rifiutato('id -- x', 'ASC')
        && rifiutato('id #', 'ASC')
        && rifiutato('id /* x */', 'ASC')
        && rifiutato("name'", 'ASC')
        && rifiutato('`name', 'ASC');
});

check('; e commenti dentro stringhe e identificatori non contano', function () {
    return Query::orderClause("FIELD(status, 'a;b', 'c--d', '#e', '/*f')", 'ASC')
            === " ORDER BY FIELD(status, 'a;b', 'c--d', '#e', '/*f') ASC"
        && Query::orderClause('`a;b`', null) === ' ORDER BY `a;b`';
});

check('barra dentro una stringa rifiutata, con o senza NO_BACKSLASH_ESCAPES', function () {
    return rifiutato("name, '\\' -- '", 'ASC')
        && rifiutato("FIELD(status, 'it\\'s', 'b')", null)
        && rifiutato('FIELD(status, "a\\"b")', null)
        && Query::orderClause("FIELD(status, 'it''s', 'b')", null) === " ORDER BY FIELD(status, 'it''s', 'b')"
        && Query::orderClause('`a\\b`', 'ASC') === ' ORDER BY `a\\b` ASC';
});

check('direzione senza order rifiutata, nessuna clausola senza entrambi', function () {
    return rifiutato(null, 'DESC')
        && rifiutato('', 'ASC')
        && rifiutato('  ', 'desc')
        && Query::orderClause(null, '') === ''
        && Query::orderClause('', null) === '';
});

check('Select si ferma prima di interrogare il database', function () {
    $query = new Query(mysqli_init());

    try {
        $query->Select('user', null, 1, 'id', 'DESC; DROP TABLE user');

        return false;
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'direzione ORDER BY');
    }
});

summary();
