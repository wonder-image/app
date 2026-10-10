<?php
/** php tests/App/Support/GoogleMapsGeoTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\GoogleMaps;

check('place ID e coordinate buoni passano', function () {
    $geo = GoogleMaps::cleanGeo(['place_id' => 'ChIJabc_def-123', 'lat' => '45.4642035', 'lng' => '9.189982']);

    return $geo === ['place_id' => 'ChIJabc_def-123', 'lat' => 45.4642035, 'lng' => 9.189982];
});

check('con il prefisso legge e restituisce le chiavi prefissate', function () {
    $geo = GoogleMaps::cleanGeo([
        'shipping_place_id' => 'ChIJabc_def-123',
        'shipping_lat' => '-33.8688',
        'shipping_lng' => '151.2093',
        'place_id' => 'ChIJaltro_000000',
    ], 'shipping_');

    return $geo === ['shipping_place_id' => 'ChIJabc_def-123', 'shipping_lat' => -33.8688, 'shipping_lng' => 151.2093];
});

check('coordinate fuori scala: tutte e due null', function () {
    return GoogleMaps::cleanGeo(['lat' => '91', 'lng' => '9'])['lat'] === null
        && GoogleMaps::cleanGeo(['lat' => '45', 'lng' => '-180.5']) === ['place_id' => null, 'lat' => null, 'lng' => null];
});

check('una sola coordinata: tutte e due null', function () {
    return GoogleMaps::cleanGeo(['lat' => '45.1', 'lng' => '']) === ['place_id' => null, 'lat' => null, 'lng' => null];
});

check('coordinate non numeriche o array: null', function () {
    return GoogleMaps::cleanGeo(['lat' => 'abc', 'lng' => '9'])['lng'] === null
        && GoogleMaps::cleanGeo(['lat' => ['45'], 'lng' => '9'])['lat'] === null
        && GoogleMaps::cleanGeo(['lat' => 'INF', 'lng' => '9'])['lat'] === null;
});

check('place ID fuori formato: null, coordinate tenute', function () {
    $bad = ['corto', str_repeat('a', 256), 'ChIJ<script>xx', 'ChIJ abc def', ['ChIJabc_def-123']];

    foreach ($bad as $value) {
        $geo = GoogleMaps::cleanGeo(['place_id' => $value, 'lat' => '1', 'lng' => '2']);

        if ($geo !== ['place_id' => null, 'lat' => 1.0, 'lng' => 2.0]) {
            return false;
        }
    }

    return true;
});

check('campi assenti: tutto null', function () {
    return GoogleMaps::cleanGeo([]) === ['place_id' => null, 'lat' => null, 'lng' => null];
});

summary();
