<?php
/** php tests/Backend/Support/ResourceOverrideTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Resources\Config\SocietyLocationResource;

check('la pagina delle sedi si può estendere da un modulo', fn () =>
    (new ReflectionClass(SocietyLocationResource::class))->isFinal() === false
);

check('una sottoclasse con lo stesso percorso ha lo stesso slug', function () {
    $sottoclasse = new class extends SocietyLocationResource {};

    return $sottoclasse::slug() === SocietyLocationResource::slug();
});

summary();
