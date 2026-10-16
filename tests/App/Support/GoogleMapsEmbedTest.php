<?php
/**
 * URL della Maps Embed API per le sedi e iframe di `Iframe::googlePlace()`.
 *
 *   php tests/App/Support/GoogleMapsEmbedTest.php
 */
declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Wonder\App\Support\GoogleMapsEmbed;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};

$place = 'ChIJu46S-ZZhLxMROG5lkwZ3D7k';
$url = GoogleMapsEmbed::forPlace($place, 'satellite', 15, 'it', 'browser-key');
parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
$check(str_starts_with($url, 'https://www.google.com/maps/embed/v1/place?'), 'Embed endpoint');
$check($query === ['key' => 'browser-key', 'q' => 'place_id:'.$place, 'maptype' => 'satellite', 'zoom' => '15', 'language' => 'it'], 'Place query');
$check(GoogleMapsEmbed::forPlace($place, 'roadmap', null, '', '') === '', 'No key, no URL');
$check(GoogleMapsEmbed::forPlace('', 'roadmap', null, '', 'browser-key') === '', 'No place, no URL');

$fallback = GoogleMapsEmbed::forLocation(['google_place_id' => '', 'lat' => '45.46', 'lng' => '9.19'], 'roadmap', null, '', 'browser-key');
parse_str((string) parse_url($fallback, PHP_URL_QUERY), $query);
$check(($query['q'] ?? '') === '45.46,9.19', 'Coordinates fallback');
$check(str_contains(GoogleMapsEmbed::forLocation((object) ['google_place_id' => $place], 'roadmap', null, '', 'browser-key'), 'place_id%3A'), 'Location object with place id');

foreach ([['hybrid', null, ''], ['roadmap', 22, ''], ['roadmap', null, 'it;x']] as [$type, $zoom, $language]) {
    try { GoogleMapsEmbed::forPlace($place, $type, $zoom, $language, 'browser-key'); $check(false, 'Invalid option accepted'); }
    catch (InvalidArgumentException) { $checks++; }
}

echo "$checks GoogleMapsEmbed checks passed.\n";
