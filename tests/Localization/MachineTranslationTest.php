<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/MemoryTranslationStore.php';
require __DIR__.'/FakeTranslator.php';

use Wonder\Localization\Machine\GoogleTranslator;
use Wonder\Localization\Machine\MachineTranslation;

function vocabularyStore(): MemoryTranslationStore
{
    $store = new MemoryTranslationStore();
    $rosso = $store->saveTerm('Rosso', ['mode' => 'glossary', 'status' => 'confirmed']);
    $store->saveTerm('Acme', ['mode' => 'keep', 'status' => 'confirmed']);
    $blu = $store->saveTerm('Blu', ['mode' => 'glossary', 'status' => 'candidate']);
    $store->save('translation_terms', $rosso, 'term', 'en', ['value' => 'Red', 'status' => 'reviewed']);
    $store->save('translation_terms', $blu, 'term', 'en', ['value' => 'Blue', 'status' => 'reviewed']);

    return $store;
}

check('composizione locale: termini e codici senza motore', function () {
    $driver = new FakeTranslator();
    $result = (new MachineTranslation(vocabularyStore(), $driver))->translate(['rosso, Acme / RAL 3020'], 'it', 'en');

    return $result[0] === ['value' => 'Red, Acme / RAL 3020', 'engine' => 'local'] && $driver->calls === [];
});

check('un candidato non vale come termine', function () {
    $driver = new FakeTranslator();
    $result = (new MachineTranslation(vocabularyStore(), $driver))->translate(['Rosso, Blu'], 'it', 'en');

    return $result[0]['engine'] === 'fake' && count($driver->calls) === 1;
});

check('protezione: glossario e keep in translate="no", poi tolti', function () {
    $driver = new FakeTranslator();
    $result = (new MachineTranslation(vocabularyStore(), $driver))->translate(['Maglia rossa Acme, colore Rosso & bianco'], 'it', 'en');
    $sent = $driver->calls[0][0];

    return str_contains($sent, '<span translate="no" data-wi-mt>Acme</span>')
        && str_contains($sent, '<span translate="no" data-wi-mt>Red</span>')
        && str_contains($sent, '&amp;')
        && $result[0]['value'] === '[en] Maglia rossa Acme, colore Red & bianco';
});

check('protezione: non sostituisce dentro le parole né nei tag', function () {
    $driver = new FakeTranslator();
    (new MachineTranslation(vocabularyStore(), $driver))->translate(['<p class="Rosso">Rossori e {{nome}}</p>'], 'it', 'en');
    $sent = $driver->calls[0][0];

    return str_contains($sent, 'class="Rosso"') && str_contains($sent, 'Rossori')
        && str_contains($sent, '<span translate="no" data-wi-mt>{{nome}}</span>');
});

check('html resta html, testo semplice torna con gli a capo', function () {
    $result = (new MachineTranslation(vocabularyStore(), new FakeTranslator()))
        ->translate(['<p>Ciao</p>', "Riga uno\nRiga \"due\""], 'it', 'en');

    return $result[0]['value'] === '[en] <p>Ciao</p>' && $result[1]['value'] === "[en] Riga uno\nRiga \"due\"";
});

check('memoria: la seconda volta non chiama il motore', function () {
    $store = vocabularyStore();
    $driver = new FakeTranslator();
    (new MachineTranslation($store, $driver))->translate(['Maglia'], 'it', 'en');
    $result = (new MachineTranslation($store, $driver))->translate(['Maglia', 'Maglia'], 'it', 'en');

    return count($driver->calls) === 1 && $result[1] === ['value' => '[en] Maglia', 'engine' => 'memory'];
});

check('duplicati nello stesso batch: un solo testo al motore', function () {
    $driver = new FakeTranslator();
    $result = (new MachineTranslation(vocabularyStore(), $driver))->translate(['Maglia', 'Felpa', 'Maglia'], 'it', 'en');

    return $driver->calls[0] === ['Maglia', 'Felpa'] && $result[2]['value'] === '[en] Maglia';
});

check('segmenti non coperti: niente codici, termini o frasi lunghe', function () {
    $machine = new MachineTranslation(vocabularyStore(), new FakeTranslator());
    $found = $machine->uncovered('<p>Rosso, Cotone bio; XL-42 | Acme / una frase molto lunga di troppe parole</p>', 'en');

    return $found === ['Cotone bio'];
});

check('Google: format html, chiave nell\'header, batch da 128', function () {
    $requests = [];
    $transport = function (string $url, string $body, array $headers) use (&$requests): string {
        $requests[] = compact('url', 'body', 'headers');
        parse_str(str_replace('q=', 'q[]=', $body), $query);

        return json_encode(['data' => ['translations' => array_map(
            static fn (string $q): array => ['translatedText' => strtoupper($q)],
            $query['q']
        )]]);
    };
    $texts = array_map(static fn (int $i): string => "testo {$i} & co", range(1, 130));
    $result = (new GoogleTranslator('KEY', $transport))->translate($texts, 'it', 'en');

    return count($requests) === 2
        && $result[129] === 'TESTO 130 & CO'
        && str_contains($requests[0]['body'], 'format=html')
        && str_contains($requests[0]['body'], 'q=testo%201%20%26%20co')
        && !str_contains($requests[0]['url'].$requests[0]['body'], 'KEY')
        && in_array('X-Goog-Api-Key: KEY', $requests[0]['headers'], true);
});

check('Google: risposta incompleta è un errore', function () {
    try {
        (new GoogleTranslator('KEY', static fn (): string => '{"data":{"translations":[]}}'))->translate(['a'], 'it', 'en');
    } catch (RuntimeException) {
        return true;
    }

    return false;
});

summary();
