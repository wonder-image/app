<?php

namespace Wonder\Localization\Catalog;

use Wonder\Localization\Catalog\Legal\DatabaseLegalDocumentStore;
use Wonder\Localization\Catalog\Legal\LegalDocumentStore;

/**
 * I documenti legali attivi di `legal_documents`. L'id è `<tipo>.name`,
 * `<tipo>.checkbox_label` o `<tipo>.blocks.<n>.<campo>` sui blocchi
 * dell'editor; la destinazione è il documento della stessa versione nella
 * lingua di destinazione. All'import quel documento si crea copiando i
 * blocchi della sorgente, oppure si aggiorna se nessuno l'ha ancora
 * accettato. Un documento accettato non si riscrive: serve una versione nuova.
 */
class LegalTranslationSource implements TranslationSource
{
    public const LOCKED = 'Versione già accettata: crea una versione nuova.';

    /** Campi di testo dei blocchi; `items` è una lista di testi. */
    private const BLOCK_FIELDS = ['text', 'caption', 'title', 'message', 'items'];

    private ?LegalDocumentStore $store;

    public function __construct(?LegalDocumentStore $store = null)
    {
        $this->store = $store;
    }

    public function key(): string
    {
        return 'legal';
    }

    public function label(): string
    {
        return 'Documenti legali';
    }

    public function entries(string $source, string $target): array
    {
        $entries = [];

        foreach ($this->store()->types() as $type => $typeLabel) {
            $document = $this->store()->active($type, $source);

            if ($document === null) {
                continue;
            }

            $translation = $this->store()->find($type, (string) $document['version'], $target);
            $locked = $translation !== null && $this->store()->accepted((int) $translation['id']) ? self::LOCKED : null;
            $context = trim($typeLabel.' v'.$document['version']);
            $targetTexts = $translation === null ? [] : $this->texts($type, $translation);

            foreach ($this->texts($type, $document) as $id => $text) {
                $entries[$id] = new Entry($id, $context, $text, $targetTexts[$id] ?? '', $type, $locked);
            }
        }

        return $entries;
    }

    public function apply(string $source, string $target, array $changes): array
    {
        $errors = [];
        $byType = [];

        foreach ($changes as $id => $value) {
            $byType[strstr($id, '.', true) ?: $id][$id] = (string) $value;
        }

        foreach ($byType as $type => $typeChanges) {
            $document = $this->store()->active((string) $type, $source);

            if ($document === null) {
                $errors += array_fill_keys(array_keys($typeChanges), 'Documento inesistente.');
                continue;
            }

            $translation = $this->store()->find((string) $type, (string) $document['version'], $target);

            if ($translation !== null && $this->store()->accepted((int) $translation['id'])) {
                $errors += array_fill_keys(array_keys($typeChanges), self::LOCKED);
                continue;
            }

            $sourceTexts = $this->texts((string) $type, $document);
            $texts = array_replace(
                $sourceTexts,
                $translation === null ? [] : array_intersect_key($this->texts((string) $type, $translation), $sourceTexts),
            );

            foreach ($typeChanges as $id => $value) {
                if (!array_key_exists($id, $sourceTexts)) {
                    $errors[$id] = 'Chiave inesistente.';
                    continue;
                }

                $texts[$id] = $value;
            }

            $blocks = $this->blocks($document);

            foreach ($texts as $id => $text) {
                if (preg_match('/^[^.]+\.blocks\.(\d+)\.(\w+)(?:\.(\d+))?$/', $id, $match) !== 1) {
                    continue;
                }

                if (isset($match[3]) && $match[3] !== '') {
                    $blocks[(int) $match[1]]['data'][$match[2]][(int) $match[3]] = $text;
                } else {
                    $blocks[(int) $match[1]]['data'][$match[2]] = $text;
                }
            }

            $content = json_encode($blocks, JSON_UNESCAPED_SLASHES);
            $values = [
                'name' => $texts["{$type}.name"],
                'checkbox_label' => $texts["{$type}.checkbox_label"],
                'content_hash' => hash('sha256', (string) $content),
                'content_snapshot' => $content,
            ];

            if ($translation === null) {
                $this->store()->insert($values + [
                    'doc_type' => $type,
                    'version' => $document['version'],
                    'language_code' => $target,
                    'published_at' => $document['published_at'] ?? date('Y-m-d H:i:s'),
                    'active' => $document['active'] ?? 'true',
                ]);
            } else {
                $this->store()->update((int) $translation['id'], $values);
            }
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function texts(string $type, array $document): array
    {
        $texts = [
            "{$type}.name" => (string) ($document['name'] ?? ''),
            "{$type}.checkbox_label" => (string) ($document['checkbox_label'] ?? ''),
        ];

        foreach ($this->blocks($document) as $index => $block) {
            foreach (self::BLOCK_FIELDS as $field) {
                $value = $block['data'][$field] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $texts["{$type}.blocks.{$index}.{$field}"] = $value;
                } elseif (is_array($value) && array_is_list($value)) {
                    foreach ($value as $itemIndex => $item) {
                        if (is_string($item) && trim($item) !== '') {
                            $texts["{$type}.blocks.{$index}.{$field}.{$itemIndex}"] = $item;
                        }
                    }
                }
            }
        }

        return $texts;
    }

    /** @return list<array> */
    private function blocks(array $document): array
    {
        $content = $document['content_snapshot'] ?? '';
        $content = is_string($content) ? json_decode($content, true) : $content;

        if (is_array($content) && isset($content['blocks']) && is_array($content['blocks'])) {
            $content = $content['blocks'];
        }

        return is_array($content) ? array_values(array_filter($content, 'is_array')) : [];
    }

    private function store(): LegalDocumentStore
    {
        return $this->store ??= new DatabaseLegalDocumentStore();
    }
}
