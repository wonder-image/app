<?php

namespace Wonder\Localization\Catalog;

/**
 * Confronto fra il foglio del traduttore e il catalogo di oggi. Ogni riga ha
 * una categoria; si applicano solo quelle scelte fra `new`, `modified` e
 * `source_changed` (quest'ultima parte deselezionata), mai gli errori. Una
 * cella vuota non cancella: la riga non entra nell'anteprima.
 */
final class ImportPreview
{
    public const NEW = 'new';
    public const MODIFIED = 'modified';
    public const UNCHANGED = 'unchanged';
    public const SOURCE_CHANGED = 'source_changed';
    public const ERROR = 'error';

    public const LABELS = [
        self::NEW => 'Nuova',
        self::MODIFIED => 'Modificata',
        self::UNCHANGED => 'Invariata',
        self::SOURCE_CHANGED => 'Sorgente cambiata',
        self::ERROR => 'Errore',
    ];

    private const FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param array<string, Entry> $entries
     * @param list<array{id: string, context?: string, source?: string, target?: string}> $rows
     * @return array<string, array{id: string, context: string, source: string, file_source: string, current: string, value: string, status: string, errors: list<string>, group: ?string}>
     */
    public static function build(array $entries, array $rows): array
    {
        $preview = [];

        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $entry = $entries[$id] ?? null;
            $value = (string) ($row['target'] ?? '');

            if ($id === '' || trim($value) === '' || isset($preview[$id])) {
                continue;
            }

            $current = $entry?->source ?? '';
            $value = self::unescape($value, $current);
            $fileSource = self::unescape((string) ($row['source'] ?? ''), $current);
            $errors = [];

            if ($entry === null) {
                $errors[] = 'Id inesistente.';
            } elseif ($value !== $entry->target) {
                if ($entry->locked !== null) {
                    $errors[] = $entry->locked;
                }

                $errors = array_merge($errors, TextRules::errors($entry->source, $value));
            }

            $status = match (true) {
                $errors !== [] => self::ERROR,
                $value === $entry->target => self::UNCHANGED,
                $fileSource !== $entry->source => self::SOURCE_CHANGED,
                $entry->target === '' => self::NEW,
                default => self::MODIFIED,
            };

            $preview[$id] = [
                'id' => $id,
                'context' => $entry?->context ?? (string) ($row['context'] ?? ''),
                'source' => $current,
                'file_source' => $fileSource,
                'current' => $entry?->target ?? '',
                'value' => $value,
                'status' => $status,
                'errors' => $errors,
                'group' => $entry?->group,
            ];
        }

        $selected = array_keys(array_filter($preview, static fn (array $row): bool => self::selectedByDefault($row)));

        foreach (self::incompleteGroups($entries, self::changes($preview, $selected)) as $group => $missing) {
            foreach ($preview as $id => $row) {
                if ($row['group'] === $group && self::selectable($row)) {
                    $preview[$id]['status'] = self::ERROR;
                    $preview[$id]['errors'][] = 'Lista incompleta: mancano '.implode(', ', $missing).'.';
                }
            }
        }

        return $preview;
    }

    public static function selectable(array $row): bool
    {
        return in_array($row['status'], [self::NEW, self::MODIFIED, self::SOURCE_CHANGED], true);
    }

    public static function selectedByDefault(array $row): bool
    {
        return in_array($row['status'], [self::NEW, self::MODIFIED], true);
    }

    /**
     * Le traduzioni da applicare fra gli id scelti: gli errori e le righe
     * invariate non passano mai, qualunque id arrivi dal client.
     *
     * @param list<string> $ids
     * @return array<string, string>
     */
    public static function changes(array $preview, array $ids): array
    {
        $changes = [];

        foreach ($ids as $id) {
            $row = $preview[(string) $id] ?? null;

            if ($row !== null && self::selectable($row)) {
                $changes[$row['id']] = $row['value'];
            }
        }

        return $changes;
    }

    /**
     * Liste che, con queste modifiche, resterebbero a metà nella lingua di
     * destinazione.
     *
     * @param array<string, Entry> $entries
     * @param array<string, string> $changes
     * @return array<string, list<string>> id mancanti per gruppo
     */
    public static function incompleteGroups(array $entries, array $changes): array
    {
        $groups = [];

        foreach (array_keys($changes) as $id) {
            if (($group = $entries[$id]?->group ?? null) !== null) {
                $groups[$group] = [];
            }
        }

        foreach ($entries as $id => $entry) {
            if ($entry->group !== null && isset($groups[$entry->group])
                && $entry->target === '' && !isset($changes[$id])) {
                $groups[$entry->group][] = $id;
            }
        }

        return array_filter($groups);
    }

    /** Toglie l'apice che l'export mette davanti alle formule, se il testo vero non lo ha. */
    private static function unescape(string $value, string $source): string
    {
        if (strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::FORMULA, true)
            && !str_starts_with($source, "'")) {
            return substr($value, 1);
        }

        return $value;
    }
}
