<?php
/** php tests/App/Media/RasterIconPlanTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Media\RasterIconPlan;

$icon = static fn (int $id, string $slug, array $files): array => ['id' => $id, 'slug' => $slug, 'file' => json_encode($files)];

/**
 * Esegue il piano in memoria come `RasterIconMigration` su disco: copia i
 * file in `images/` senza sovrascrivere e toglie dalle icone le righe
 * passate a immagine. Ritorna lo stato dopo il giro.
 */
$apply = static function (array $state) use ($icon): array {
    $plan = RasterIconPlan::plan(
        $state['icons'],
        array_keys($state['iconFiles']),
        array_keys($state['imageFiles']),
        $state['imageSlugs'],
        static fn (string $from, string $to): bool => $state['iconFiles'][$from] === $state['imageFiles'][$to]
    );

    foreach ($plan['migrate'] as $step) {
        foreach ($step['copies'] as [$from, $to]) {
            if (isset($state['imageFiles'][$to]) && $state['imageFiles'][$to] !== $state['iconFiles'][$from]) {
                throw new RuntimeException("sovrascritto {$to}");
            }

            $state['imageFiles'][$to] = $state['iconFiles'][$from];
        }

        $state['icons'] = array_values(array_filter($state['icons'], static fn (array $row): bool => $row['id'] !== $step['id']));
        $state['imageSlugs'][] = $step['slug'];
    }

    return array_merge($state, ['plan' => $plan]);
};

check('un\'icona raster passa a immagine con il suo file e le sue misure', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'carrello', ['carrello.png'])],
        ['carrello.png', 'carrello.webp', 'carrello-240.png', 'carrello-240.webp', 'carrello-480.png', 'altro.png'],
        []
    );

    return $plan['skipped'] === [] && $plan['migrate'] === [[
        'id' => 1,
        'slug' => 'carrello',
        'file' => ['carrello.png'],
        'copies' => [
            ['carrello-240.png', 'carrello-240.png'],
            ['carrello-240.webp', 'carrello-240.webp'],
            ['carrello-480.png', 'carrello-480.png'],
            ['carrello.png', 'carrello.png'],
            ['carrello.webp', 'carrello.webp'],
        ],
        'renamed' => false,
    ]];
});

check('le icone svg restano icone, anche con il nome in maiuscolo', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'logo', ['logo.svg']), $icon(2, 'freccia', ['FRECCIA.SVG'])],
        ['logo.svg', 'FRECCIA.SVG'],
        []
    );

    return $plan === ['migrate' => [], 'skipped' => []];
});

check('una riga senza file, o con un file illeggibile, non si tocca', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [
            $icon(1, 'vuota', []),
            ['id' => 2, 'slug' => 'rotta', 'file' => 'non json'],
            ['id' => 3, 'slug' => 'nulla', 'file' => null],
            $icon(4, 'percorso', ['../images/altro.png']),
        ],
        ['altro.png'],
        []
    );

    return $plan === ['migrate' => [], 'skipped' => []];
});

check('le misure di un altro media non si prendono per proprie', function () use ($icon) {
    // `casa-240.png` è l'originale dell'icona 2, non la misura 240 dell'icona 1.
    $plan = RasterIconPlan::plan(
        [$icon(1, 'casa', ['casa.png']), $icon(2, 'casa-240', ['casa-240.png'])],
        ['casa.png', 'casa-240.png', 'casa-480.png', 'casa-240-240.png'],
        []
    );

    return array_column($plan['migrate'][0]['copies'], 0) === ['casa-480.png', 'casa.png']
        && array_column($plan['migrate'][1]['copies'], 0) === ['casa-240-240.png', 'casa-240.png'];
});

check('un file con un nome simile non è una misura', function () {
    $copies = RasterIconPlan::copies('casa.png', '', array_fill_keys(
        ['casa.png', 'casa-mia.png', 'casa-240x240.png', 'casa-240.jpg', 'casetta.png', 'casa.png.bak', 'xcasa.png'],
        true
    ));

    return $copies === [['casa.png', 'casa.png']];
});

check('nome già occupato in images da un file diverso: l\'icona prende un nome nuovo', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'carrello', ['carrello.png'])],
        ['carrello.png', 'carrello-240.png', 'carrello.webp'],
        ['carrello.png']
    );
    $step = $plan['migrate'][0];

    return $step['renamed'] === true
        && $step['file'] === ['carrello-icona.png']
        && $step['slug'] === 'carrello'
        && $step['copies'] === [
            ['carrello-240.png', 'carrello-icona-240.png'],
            ['carrello.png', 'carrello-icona.png'],
            ['carrello.webp', 'carrello-icona.webp'],
        ];
});

check('basta una misura occupata per cambiare nome a tutti i file', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'carrello', ['carrello.png'])],
        ['carrello.png', 'carrello-240.webp'],
        ['carrello-240.webp']
    );

    return array_column($plan['migrate'][0]['copies'], 1) === ['carrello-icona-240.webp', 'carrello-icona.png'];
});

check('anche il nome nuovo può essere occupato: si prova il successivo', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'carrello', ['carrello.png'])],
        ['carrello.png'],
        ['carrello.png', 'carrello-icona.png', 'carrello-icona2.png']
    );

    return $plan['migrate'][0]['file'] === ['carrello-icona3.png'];
});

check('immagine con lo stesso slug: cambiano slug e nome del file', function () use ($icon) {
    $plan = RasterIconPlan::plan(
        [$icon(1, 'carrello', ['carrello.png'])],
        ['carrello.png'],
        [],
        ['carrello']
    );
    $step = $plan['migrate'][0];

    return $step['slug'] === 'carrello-icona' && $step['file'] === ['carrello-icona.png'] && $step['renamed'] === true;
});

check('due icone non finiscono sullo stesso file', function () use ($icon) {
    // La seconda riga punta a un file che, rinominato, coinciderebbe con quello della prima.
    $plan = RasterIconPlan::plan(
        [$icon(1, 'a', ['carrello.png']), $icon(2, 'b', ['carrello-icona.png'])],
        ['carrello.png', 'carrello-icona.png'],
        ['carrello.png']
    );
    $targets = array_merge(...array_map(static fn (array $step): array => array_column($step['copies'], 1), $plan['migrate']));

    return count($plan['migrate']) === 2 && count($targets) === count(array_unique($targets));
});

check('file sparito da icons: la riga cambia tipo solo se il suo nome in images è libero', function () use ($icon) {
    $free = RasterIconPlan::plan([$icon(1, 'perso', ['perso.png'])], [], []);
    $taken = RasterIconPlan::plan([$icon(1, 'perso', ['perso.png'])], [], ['perso.png']);

    return $free['migrate'][0]['copies'] === [] && $free['migrate'][0]['file'] === ['perso.png']
        && $taken['migrate'] === [] && $taken['skipped'][0]['id'] === 1;
});

check('nessun nome libero: la riga resta icona e viene segnalata', function () use ($icon) {
    $occupied = ['carrello.png', 'carrello-icona.png'];

    foreach (range(2, 60) as $number) {
        $occupied[] = "carrello-icona{$number}.png";
    }

    $plan = RasterIconPlan::plan([$icon(1, 'carrello', ['carrello.png'])], ['carrello.png'], $occupied);

    return $plan['migrate'] === [] && $plan['skipped'] === [['id' => 1, 'reason' => 'nessun nome libero in images/']];
});

$state = [
    'icons' => [
        $icon(1, 'carrello', ['carrello.png']),
        $icon(2, 'logo', ['logo.svg']),
        $icon(3, 'cuore', ['cuore.jpg']),
    ],
    'iconFiles' => [
        'carrello.png' => 'icona carrello', 'carrello-240.png' => 'icona carrello 240',
        'logo.svg' => 'svg', 'cuore.jpg' => 'icona cuore', 'cuore.webp' => 'icona cuore webp',
    ],
    'imageFiles' => ['carrello.png' => 'foto di un carrello', 'spiaggia.jpg' => 'foto'],
    'imageSlugs' => ['spiaggia'],
];

check('un giro completo non sovrascrive e non cancella niente', function () use ($apply, $state) {
    $after = $apply($state);

    return $after['imageFiles'] === [
        'carrello.png' => 'foto di un carrello',
        'spiaggia.jpg' => 'foto',
        'carrello-icona-240.png' => 'icona carrello 240',
        'carrello-icona.png' => 'icona carrello',
        'cuore.jpg' => 'icona cuore',
        'cuore.webp' => 'icona cuore webp',
    ]
        && $after['iconFiles'] === $state['iconFiles']
        && array_column($after['icons'], 'id') === [2];
});

check('il secondo giro non fa niente', function () use ($apply, $state) {
    $second = $apply($apply($state));

    return $second['plan'] === ['migrate' => [], 'skipped' => []];
});

check('giro interrotto a metà: i file già copiati non sono collisioni', function () use ($apply, $state) {
    // Copiato l'originale del cuore, poi il processo si è fermato prima di cambiare la riga.
    $state['imageFiles']['cuore.jpg'] = 'icona cuore';
    $after = $apply($state);
    $step = array_values(array_filter($after['plan']['migrate'], static fn (array $step): bool => $step['id'] === 3))[0];

    return $step['renamed'] === false && $step['file'] === ['cuore.jpg']
        && $after['imageFiles']['cuore.webp'] === 'icona cuore webp';
});

summary();
