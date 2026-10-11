<?php
/** php tests/App/Support/GooglePlacesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Scheduler\Tasks\GooglePlacesTask;
use Wonder\App\Support\GooglePlaces\GooglePlaceData;
use Wonder\App\Support\GooglePlaces\GooglePlacesStore;
use Wonder\App\Support\GooglePlaces\GooglePlacesSync;
use Wonder\App\Support\GooglePlaces\PlaceDetailsClient;
use Wonder\App\Support\GooglePlaces\PlaceDiff;
use Wonder\App\Support\GooglePlaces\PlaceMapper;
use Wonder\App\Support\GooglePlaces\PlaceSyncPlan;
use Wonder\Elements\Components\GoogleReviews;
use Wonder\Themes\Support\PageAssets;

date_default_timezone_set('Europe/Rome');

function gp_point(int $day, int $hour, int $minute = 0, ?string $date = null): array
{
    $point = ['day' => $day, 'hour' => $hour, 'minute' => $minute];

    if ($date !== null) {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $point['date'] = ['year' => $y, 'month' => $m, 'day' => $d];
    }

    return $point;
}

function gp_date(string $date): array
{
    [$y, $m, $d] = array_map('intval', explode('-', $date));

    return ['year' => $y, 'month' => $m, 'day' => $d];
}

/** Lun-Ven 09-18 su Google (domenica = 0). */
function gp_place(array $extra = []): array
{
    $periods = [];

    for ($day = 1; $day <= 5; $day++) {
        $periods[] = ['open' => gp_point($day, 9), 'close' => gp_point($day, 18)];
    }

    return $extra + [
        'businessStatus' => 'OPERATIONAL',
        'regularOpeningHours' => ['periods' => $periods],
        'rating' => 4.66,
        'userRatingCount' => 123,
        'googleMapsUri' => 'https://maps.google.com/?cid=1',
        'reviews' => [[
            'name' => 'places/abc/reviews/1',
            'rating' => 5,
            'text' => ['text' => 'Ottimo 😀', 'languageCode' => 'it'],
            'originalText' => ['text' => 'Ottimo 😀', 'languageCode' => 'it'],
            'authorAttribution' => ['displayName' => 'Mario', 'uri' => 'https://www.google.com/maps/contrib/1', 'photoUri' => 'javascript:alert(1)'],
            'publishTime' => '2026-10-01T10:00:00Z',
            'relativePublishTimeDescription' => '2 settimane fa',
        ]],
    ];
}

function gp_local_hours(): array
{
    $rows = [];

    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $day) {
        $rows[] = ['hours_type' => 'regular', 'open_day' => $day, 'open_time' => '09:00', 'close_day' => $day, 'close_time' => '18:00'];
    }

    return $rows;
}

// --- PlaceMapper ----------------------------------------------------------

check('mapper: giorni da domenica, stato, voto, URL solo https', function () {
    $mapped = PlaceMapper::map(gp_place());

    return count($mapped['hours']) === 5
        && $mapped['hours'][0]['open_day'] === 'Mon'
        && $mapped['hours'][4]['open_day'] === 'Fri'
        && $mapped['hours'][0]['position'] === '1'
        && $mapped['hours_types'] === ['regular']
        && $mapped['business_status'] === 'operational'
        && $mapped['rating'] === 4.7
        && $mapped['rating_count'] === 123
        && $mapped['has_special_hours'] === false
        && $mapped['reviews'][0]['author_photo_uri'] === ''
        && $mapped['reviews'][0]['author_uri'] === 'https://www.google.com/maps/contrib/1'
        && $mapped['reviews'][0]['publish_time'] === '2026-10-01 12:00:00';
});

check('mapper: chiusura a mezzanotte, notte, sempre aperto, più giorni', function () {
    $midnight = PlaceMapper::hoursRows('regular', [['open' => gp_point(6, 18), 'close' => gp_point(0, 0)]]);
    $overnight = PlaceMapper::hoursRows('regular', [['open' => gp_point(5, 22), 'close' => gp_point(6, 2)]]);
    $always = PlaceMapper::hoursRows('regular', [['open' => gp_point(0, 0)]]);
    $multi = PlaceMapper::hoursRows('regular', [['open' => gp_point(5, 20), 'close' => gp_point(0, 3)]]);

    return $midnight === [['hours_type' => 'regular', 'open_day' => 'Sat', 'open_time' => '18:00', 'close_day' => 'Sat', 'close_time' => '24:00']]
        && $overnight[0]['close_day'] === 'Sat' && $overnight[0]['close_time'] === '02:00'
        && $always === [['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '00:00', 'close_day' => '', 'close_time' => '']]
        && array_map(fn ($r) => $r['open_day'].' '.$r['open_time'].'-'.$r['close_time'], $multi) === ['Fri 20:00-24:00', 'Sat 00:00-24:00', 'Sun 00:00-03:00'];
});

check('mapper: orari secondari e tipi sconosciuti', function () {
    $mapped = PlaceMapper::map(gp_place(['regularSecondaryOpeningHours' => [
        ['secondaryHoursType' => 'DELIVERY', 'periods' => [['open' => gp_point(1, 11), 'close' => gp_point(1, 14)]]],
        ['secondaryHoursType' => 'NUOVO_TIPO', 'periods' => [['open' => gp_point(1, 11), 'close' => gp_point(1, 14)]]],
    ]]));

    return $mapped['hours_types'] === ['regular', 'delivery']
        && count($mapped['hours']) === 6
        && $mapped['hours'][5]['hours_type'] === 'delivery'
        && count($mapped['skipped']) === 1 && str_contains($mapped['skipped'][0], 'nuovo_tipo');
});

check('mapper: giorni speciali chiusi e con fasce', function () {
    $mapped = PlaceMapper::map(gp_place(['currentOpeningHours' => [
        'periods' => [
            ['open' => gp_point(2, 10, 0, '2026-10-13'), 'close' => gp_point(2, 12, 0, '2026-10-13')],
        ],
        'specialDays' => [['date' => gp_date('2026-10-14')], ['date' => gp_date('2026-10-13')]],
    ]]));

    return $mapped['has_special_hours'] === true
        && array_map(fn ($r) => $r['start_date'].' '.$r['closed'].' '.$r['open_time'].'-'.$r['close_time'].' '.$r['source'], $mapped['special_hours'])
            === ['2026-10-13 false 10:00-12:00 google', '2026-10-14 true - google'];
});

// --- PlaceDiff ------------------------------------------------------------

check('diff: nessuna differenza con gli stessi orari e stato', function () {
    $google = PlaceMapper::map(gp_place());

    return PlaceDiff::compare(['hours' => gp_local_hours(), 'business_status' => 'operational'], $google, new DateTimeImmutable('2026-10-12')) === [];
});

check('diff: un giorno diverso, lo stato e un tipo assente sul sito', function () {
    $local = gp_local_hours();
    $local[0]['close_time'] = '17:00';
    $google = PlaceMapper::map(gp_place([
        'businessStatus' => 'CLOSED_TEMPORARILY',
        'regularSecondaryOpeningHours' => [['secondaryHoursType' => 'TAKEOUT', 'periods' => [['open' => gp_point(1, 12), 'close' => gp_point(1, 13)]]]],
    ]));
    $kinds = array_column(PlaceDiff::compare(['hours' => $local, 'business_status' => 'operational'], $google, new DateTimeImmutable('2026-10-12')), 'kind');

    return $kinds === ['hours:regular', 'hours:takeout', 'status']
        && PlaceDiff::changedHoursTypes($local, $google) === ['regular', 'takeout'];
});

check('diff: tipi di orario non restituiti da Google ignorati', function () {
    $local = array_merge(gp_local_hours(), [['hours_type' => 'kitchen', 'open_day' => 'Mon', 'open_time' => '12:00', 'close_day' => 'Mon', 'close_time' => '14:00']]);

    return PlaceDiff::compare(['hours' => $local], PlaceMapper::map(gp_place(['businessStatus' => null])), new DateTimeImmutable('2026-10-12')) === [];
});

check('diff: giorno speciale di Google assente sul sito', function () {
    $google = PlaceMapper::map(gp_place(['businessStatus' => null, 'currentOpeningHours' => ['periods' => [], 'specialDays' => [['date' => gp_date('2026-10-14')]]]]));
    $differences = PlaceDiff::compare(['hours' => gp_local_hours()], $google, new DateTimeImmutable('2026-10-12'));

    return count($differences) === 1 && str_starts_with($differences[0]['kind'], 'special');
});

// --- PlaceSyncPlan --------------------------------------------------------

check('piano: senza apply solo differenze, nulla da scrivere', function () {
    $local = gp_local_hours();
    $local[0]['close_time'] = '17:00';
    $plan = PlaceSyncPlan::make(['hours' => $local], PlaceMapper::map(gp_place()), new DateTimeImmutable('2026-10-12'), false);

    return $plan['hours'] === null && $plan['special_hours'] === null && count($plan['differences']) === 2;
});

check('piano: apply sostituisce solo i tipi cambiati, lo stato resta una differenza', function () {
    $local = gp_local_hours();
    $local[0]['close_time'] = '17:00';
    $local[] = ['hours_type' => 'kitchen', 'open_day' => 'Mon', 'open_time' => '12:00', 'close_day' => 'Mon', 'close_time' => '14:00'];
    $plan = PlaceSyncPlan::make(['hours' => $local, 'business_status' => 'operational'], PlaceMapper::map(gp_place(['businessStatus' => 'CLOSED_TEMPORARILY'])), new DateTimeImmutable('2026-10-12'), true);
    $types = array_column((array) $plan['hours'], 'hours_type');

    return $plan['hours'] !== null
        && $types === ['kitchen', 'regular', 'regular', 'regular', 'regular', 'regular']
        && $plan['hours'][1]['close_time'] === '18:00'
        && array_column($plan['hours'], 'position') === ['1', '2', '3', '4', '5', '6']
        && $plan['special_hours'] === null
        && array_column($plan['differences'], 'kind') === ['status'];
});

check('piano: riga manuale blocca la data, righe Google passate restano', function () {
    $special = [
        ['start_date' => '2026-10-01', 'end_date' => '', 'closed' => 'true', 'source' => 'google'],
        ['start_date' => '2026-10-13', 'end_date' => '', 'closed' => 'true', 'source' => 'google'],
        ['start_date' => '2026-10-14', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '12:00', 'note' => 'Inventario', 'source' => 'manual'],
    ];
    $google = PlaceMapper::map(gp_place(['businessStatus' => null, 'currentOpeningHours' => [
        'periods' => [],
        'specialDays' => [['date' => gp_date('2026-10-14')], ['date' => gp_date('2026-10-15')]],
    ]]));
    $plan = PlaceSyncPlan::make(['hours' => gp_local_hours(), 'special_hours' => $special], $google, new DateTimeImmutable('2026-10-12'), true);
    $rows = array_map(fn ($r) => $r['start_date'].' '.$r['source'], (array) $plan['special_hours']);

    return $plan['hours'] === null
        && $rows === ['2026-10-01 google', '2026-10-14 manual', '2026-10-15 google'];
});

check('piano: sede che eredita riceve orari e speciali propri', function () {
    $google = PlaceMapper::map(gp_place(['businessStatus' => null, 'currentOpeningHours' => ['periods' => [], 'specialDays' => []]]));
    $inherited = gp_local_hours();
    $inherited[2]['open_time'] = '08:00';
    $plan = PlaceSyncPlan::make([
        'hours' => $inherited,
        'special_hours' => [['start_date' => '2026-12-25', 'closed' => 'true', 'note' => 'Natale']],
        'inherited' => true,
    ], $google, new DateTimeImmutable('2026-10-12'), true);

    return count((array) $plan['hours']) === 5
        && $plan['hours'][2]['open_time'] === '09:00'
        && array_column((array) $plan['special_hours'], 'note') === ['Natale']
        && $plan['special_hours'][0]['source'] === 'manual'
        && $plan['differences'] === [];
});

check('piano: sede che eredita senza differenze resta ereditata', function () {
    $plan = PlaceSyncPlan::make(['hours' => gp_local_hours(), 'inherited' => true], PlaceMapper::map(gp_place(['businessStatus' => null])), new DateTimeImmutable('2026-10-12'), true);

    return $plan['hours'] === null && $plan['special_hours'] === null;
});

// --- GooglePlaceData ------------------------------------------------------

check('dati: copia solo con lo stesso place_id, entità decodificate', function () {
    $row = [
        'place_id' => 'ChIJ1',
        'rating' => '4.5',
        'rating_count' => '10',
        'maps_uri' => 'https://maps.google.com/?cid=1',
        'differences' => json_encode([['kind' => 'status', 'message' => 'Città &#8364;']]),
        'synced_at' => '2026-10-11 03:30:00',
    ];
    $snapshot = GooglePlaceData::snapshot($row, 'ChIJ1');

    return GooglePlaceData::snapshot($row, 'ChIJ2') === null
        && GooglePlaceData::snapshot($row, '') === null
        && GooglePlaceData::snapshot(null, 'ChIJ1') === null
        && $snapshot['rating'] === 4.5 && $snapshot['rating_count'] === 10
        && $snapshot['differences'][0]['message'] === 'Città €';
});

check('dati: recensioni dalla più recente, emoji tornate caratteri, &amp; intatto', function () {
    $reviews = GooglePlaceData::reviews([
        ['name' => 'a', 'rating' => '4', 'text' => 'Bene &#128512; &amp; &#233;', 'publish_time' => '2026-09-01 10:00:00'],
        ['name' => 'b', 'rating' => '5', 'text' => 'Ottimo', 'publish_time' => '2026-10-01 10:00:00'],
        ['name' => 'c', 'deleted' => 'true'],
    ]);

    return array_column($reviews, 'name') === ['b', 'a']
        && $reviews[1]['text'] === 'Bene 😀 &amp; &#233;'
        && $reviews[1]['rating'] === 4;
});

// --- GooglePlacesSync -----------------------------------------------------

final class FakePlacesStore implements GooglePlacesStore
{
    public array $hours = [];
    public array $snapshots = [];
    public array $reviews = [];
    public array $errors = [];
    public array $cleared = [];

    public function __construct(public array $locations, public array $stored = [])
    {
    }

    public function locations(): array { return $this->locations; }
    public function storedLocationIds(): array { return $this->stored; }
    public function replaceHours(int $locationId, ?array $hours, ?array $specialHours): void { $this->hours[$locationId] = [$hours, $specialHours]; }
    public function saveSnapshot(int $locationId, array $snapshot): void { $this->snapshots[$locationId] = $snapshot; }
    public function saveReviews(int $locationId, array $reviews): void { $this->reviews[$locationId] = $reviews; }
    public function saveError(int $locationId, string $error): void { $this->errors[$locationId] = $error; }
    public function clear(int $locationId): void { $this->cleared[] = $locationId; }
}

final class FakePlacesClient implements PlaceDetailsClient
{
    public array $calls = [];

    public function __construct(private array $places)
    {
    }

    public function details(string $placeId, string $language): array
    {
        $this->calls[] = [$placeId, $language];

        if (!isset($this->places[$placeId])) {
            throw new RuntimeException('NOT_FOUND');
        }

        return $this->places[$placeId];
    }
}

function gp_location(int $id, string $placeId, array $hours = []): array
{
    return ['id' => $id, 'label' => 'Sede '.$id, 'place_id' => $placeId, 'business_status' => 'operational', 'hours' => $hours ?: gp_local_hours(), 'special_hours' => [], 'inherited' => false];
}

check('sync: errore su una sede non ferma le altre, copie orfane cancellate', function () {
    $store = new FakePlacesStore([gp_location(1, 'ok'), gp_location(2, 'ko'), gp_location(3, '')], [1, 3, 9]);
    $client = new FakePlacesClient(['ok' => gp_place()]);
    $log = [];
    $summary = (new GooglePlacesSync($client, $store))->run(false, 'it', function (string $line) use (&$log): void { $log[] = $line; }, null, new DateTimeImmutable('2026-10-12'));

    return $summary === ['locations' => 2, 'synced' => 1, 'failed' => 1, 'applied' => 0, 'differences' => 0, 'cleared' => 2]
        && $store->cleared === [3, 9]
        && $store->hours === []
        && $store->snapshots[1]['place_id'] === 'ok'
        && $store->snapshots[1]['rating'] === 4.7
        && count($store->reviews[1]) === 1
        && $store->errors === [2 => 'NOT_FOUND']
        && $client->calls === [['ok', 'it'], ['ko', 'it']]
        && count(array_filter($log, fn ($l) => str_contains($l, 'Sede 2: errore'))) === 1;
});

check('sync: apply scrive gli orari e salva le differenze rimaste', function () {
    $hours = gp_local_hours();
    $hours[0]['close_time'] = '17:00';
    $store = new FakePlacesStore([gp_location(1, 'ok', $hours)]);
    $summary = (new GooglePlacesSync(new FakePlacesClient(['ok' => gp_place(['businessStatus' => 'CLOSED_TEMPORARILY'])]), $store))
        ->run(true, 'it', null, null, new DateTimeImmutable('2026-10-12'));

    return $summary['applied'] === 1
        && $store->hours[1][0][0]['close_time'] === '18:00'
        && $store->hours[1][1] === null
        && array_column($store->snapshots[1]['differences'], 'kind') === ['status'];
});

check('sync: tutte le sedi in errore fanno fallire l\'esecuzione', function () {
    $store = new FakePlacesStore([gp_location(1, 'ko')]);

    try {
        (new GooglePlacesSync(new FakePlacesClient([]), $store))->run(false, 'it');
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), '1 errori') && isset($store->errors[1]);
    }

    return false;
});

check('sync: nessun place_id, il client non si crea', function () {
    $created = false;
    $summary = (new GooglePlacesSync(function () use (&$created): PlaceDetailsClient {
        $created = true;

        throw new RuntimeException('GCP_API_KEY mancante');
    }, new FakePlacesStore([gp_location(1, '')])))->run(false, 'it');

    return $created === false && $summary['locations'] === 0;
});

check('sync: chiave mancante ferma tutto prima delle sedi', function () {
    $store = new FakePlacesStore([gp_location(1, 'ok')]);

    try {
        (new GooglePlacesSync(fn (): PlaceDetailsClient => throw new RuntimeException('GCP_API_KEY mancante'), $store))->run(false, 'it');
    } catch (RuntimeException $exception) {
        return $exception->getMessage() === 'GCP_API_KEY mancante' && $store->errors === [];
    }

    return false;
});

// --- GooglePlacesTask -----------------------------------------------------

check('task: spento, parametri di default e validazione', function () {
    $task = new GooglePlacesTask();
    $defaults = $task->validate([]);
    $valid = $task->validate(['apply_hours' => true, 'language' => 'en-GB']);
    $errors = 0;

    foreach ([['apply_hours' => 'si'], ['language' => 'it;drop'], ['altro' => 1], ['language' => 5]] as $parameters) {
        try {
            $task->validate($parameters);
        } catch (Throwable) {
            $errors++;
        }
    }

    return $task->key() === 'wonder.google-places'
        && $task->enabled() === false
        && $defaults === ['apply_hours' => false, 'language' => '']
        && $valid === ['apply_hours' => true, 'language' => 'en-GB']
        && $errors === 4;
});

// --- GoogleReviews --------------------------------------------------------

check('recensioni: attribuzioni nei due temi, testo escapato, URL non https scartati', function () {
    $element = GoogleReviews::fromLocation((object) [
        'google' => ['rating' => 4.66, 'rating_count' => 12, 'maps_uri' => 'https://maps.google.com/?cid=1'],
        'reviews' => [
            ['rating' => 4, 'text' => '<b>x</b>', 'author_name' => 'Ada', 'author_uri' => 'javascript:alert(1)', 'author_photo_uri' => 'https://lh3.example/a.jpg', 'maps_uri' => 'https://maps.google.com/r/1', 'language' => 'it', 'original_text' => 'orig', 'original_language' => 'en'],
            ['text' => 'senza autore'],
        ],
    ]);
    $ok = true;

    foreach (['wonder', 'bootstrap'] as $theme) {
        PageAssets::reset();
        $html = $element->render($theme);
        $ok = $ok
            && str_contains($html, '&lt;b&gt;x&lt;/b&gt;')
            && !str_contains($html, 'javascript:')
            && !str_contains($html, 'senza autore')
            && str_contains($html, 'src="https://lh3.example/a.jpg"')
            && str_contains($html, 'href="https://maps.google.com/r/1"')
            && str_contains($html, '<p class="wi-google-reviews__attribution" translate="no">Google Maps</p>')
            && str_contains($html, '4,7')
            && str_contains($html, '<details')
            && substr_count($element->render($theme), '<style') === 0;
    }

    return $ok;
});

check('recensioni: nulla da mostrare, nessun markup', function () {
    return GoogleReviews::make()->render('wonder') === '' && GoogleReviews::make([['rating' => 5]])->render('bootstrap') === '';
});

summary();
