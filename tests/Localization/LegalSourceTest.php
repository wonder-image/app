<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Localization\Catalog\ImportPreview;
use Wonder\Localization\Catalog\Legal\LegalDocumentStore;
use Wonder\Localization\Catalog\LegalTranslationSource;

final class MemoryLegalStore implements LegalDocumentStore
{
    public array $rows = [];
    public array $accepted = [];

    public function types(): array
    {
        return ['privacy-policy' => 'Privacy policy'];
    }

    public function active(string $type, string $lang): ?array
    {
        foreach (array_reverse($this->rows, true) as $row) {
            if ($row['doc_type'] === $type && $row['language_code'] === $lang && $row['active'] === 'true') {
                return $row;
            }
        }

        return null;
    }

    public function find(string $type, string $version, string $lang): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['doc_type'] === $type && $row['version'] === $version && $row['language_code'] === $lang) {
                return $row;
            }
        }

        return null;
    }

    public function accepted(int $id): bool
    {
        return in_array($id, $this->accepted, true);
    }

    public function insert(array $row): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = ['id' => $id] + $row;

        return $id;
    }

    public function update(int $id, array $values): void
    {
        $this->rows[$id] = array_replace($this->rows[$id], $values);
    }
}

$blocks = [
    ['id' => 'a', 'type' => 'header', 'data' => ['text' => 'Titolo', 'level' => 2]],
    ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'Testo <b>{{site}}</b>'], 'tunes' => ['textAlign' => ['alignment' => 'left']]],
    ['id' => 'c', 'type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Uno', 'Due']]],
];

$store = new MemoryLegalStore();
$store->insert([
    'doc_type' => 'privacy-policy', 'name' => 'Informativa', 'version' => '1.0.0', 'language_code' => 'it',
    'checkbox_label' => 'Accetto', 'content_hash' => '', 'content_snapshot' => json_encode($blocks),
    'published_at' => '2026-01-01 00:00:00', 'active' => 'true',
]);
$source = new LegalTranslationSource($store);

check('le righe vengono dai blocchi del documento attivo', function () use ($source) {
    $entries = $source->entries('it', 'en');

    return array_keys($entries) === [
        'privacy-policy.name', 'privacy-policy.checkbox_label', 'privacy-policy.blocks.0.text',
        'privacy-policy.blocks.1.text', 'privacy-policy.blocks.2.items.0', 'privacy-policy.blocks.2.items.1',
    ] && $entries['privacy-policy.blocks.1.text']->source === 'Testo <b>{{site}}</b>'
        && $entries['privacy-policy.name']->target === ''
        && $entries['privacy-policy.name']->context === 'Privacy policy v1.0.0';
});

check('un documento nuovo va importato per intero', function () use ($source) {
    $entries = $source->entries('it', 'en');
    $preview = ImportPreview::build($entries, [['id' => 'privacy-policy.name', 'source' => 'Informativa', 'target' => 'Privacy']]);

    return $preview['privacy-policy.name']['status'] === ImportPreview::ERROR;
});

$changes = [
    'privacy-policy.name' => 'Privacy notice',
    'privacy-policy.checkbox_label' => 'I agree',
    'privacy-policy.blocks.0.text' => 'Title',
    'privacy-policy.blocks.1.text' => 'Text <b>{{site}}</b>',
    'privacy-policy.blocks.2.items.0' => 'One',
    'privacy-policy.blocks.2.items.1' => 'Two',
];

check('crea la traduzione con la stessa versione e la struttura dei blocchi', function () use ($source, $store, $changes) {
    $errors = $source->apply('it', 'en', $changes);
    $created = $store->find('privacy-policy', '1.0.0', 'en');
    $blocks = json_decode($created['content_snapshot'], true);

    return $errors === [] && $created['name'] === 'Privacy notice'
        && $blocks[1]['tunes']['textAlign']['alignment'] === 'left'
        && $blocks[2]['data']['items'] === ['One', 'Two'] && $blocks[0]['data']['level'] === 2
        && $created['content_hash'] === hash('sha256', $created['content_snapshot']);
});

check('aggiorna sul posto un documento non accettato', function () use ($source, $store) {
    $errors = $source->apply('it', 'en', ['privacy-policy.blocks.0.text' => 'Heading']);
    $doc = $store->find('privacy-policy', '1.0.0', 'en');
    $blocks = json_decode($doc['content_snapshot'], true);

    return $errors === [] && count($store->rows) === 2 && $blocks[0]['data']['text'] === 'Heading'
        && $blocks[1]['data']['text'] === 'Text <b>{{site}}</b>';
});

check('una versione accettata è bloccata', function () use ($source, $store) {
    $store->accepted[] = 2;
    $entries = $source->entries('it', 'en');
    $errors = $source->apply('it', 'en', ['privacy-policy.name' => 'X']);
    $preview = ImportPreview::build($entries, [['id' => 'privacy-policy.name', 'source' => 'Informativa', 'target' => 'X']]);

    return $entries['privacy-policy.name']->locked === LegalTranslationSource::LOCKED
        && $errors === ['privacy-policy.name' => LegalTranslationSource::LOCKED]
        && $store->find('privacy-policy', '1.0.0', 'en')['name'] === 'Privacy notice'
        && $preview['privacy-policy.name']['status'] === ImportPreview::ERROR;
});

summary();
