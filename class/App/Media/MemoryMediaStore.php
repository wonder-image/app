<?php

namespace Wonder\App\Media;

/**
 * Media in memoria, con le stesse regole di `DatabaseMediaStore`: per i
 * test, senza database e senza file. `$failWith` fa fallire la prossima
 * scrittura di un file con quel motivo.
 */
final class MemoryMediaStore implements MediaStore
{
    /** @var array<int, array{id: int, type: string, slug: string, name: string, alt: string, file: string, deleted?: bool}> */
    public array $rows = [];

    public string $failWith = '';

    /** Quante volte è stato letto: una lettura per gruppo di id. */
    public int $reads = 0;

    private int $nextId = 1;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(array $rows = [])
    {
        foreach ($rows as $row) {
            $this->add($row);
        }
    }

    /** @param array<string, mixed> $row */
    public function add(array $row): int
    {
        $id = (int) ($row['id'] ?? $this->nextId);
        $this->nextId = max($this->nextId, $id + 1);
        $this->rows[$id] = array_replace(
            ['type' => MediaTypes::IMAGE, 'slug' => '', 'name' => '', 'alt' => '', 'file' => ''],
            $row,
            ['id' => $id]
        );

        return $id;
    }

    public function rows(array $ids): array
    {
        $this->reads++;
        $rows = [];

        foreach ($ids as $id) {
            if (isset($this->rows[$id]) && empty($this->rows[$id]['deleted'])) {
                $rows[(int) $id] = $this->rows[$id];
            }
        }

        return $rows;
    }

    public function slugTaken(string $slug): bool
    {
        foreach ($this->rows as $row) {
            if ($row['slug'] === $slug && empty($row['deleted'])) {
                return true;
            }
        }

        return false;
    }

    public function create(string $type, array $values, array $upload): array
    {
        if ($this->failWith !== '') {
            return ['id' => 0, 'error' => $this->failWith];
        }

        return ['id' => $this->add([
            'type' => $type,
            'slug' => (string) $values['slug'],
            'name' => (string) $values['name'],
            'alt' => (string) $values['alt'],
            'file' => $values['slug'].'.'.MediaTypes::extension(MediaUpload::fileName($upload)),
        ]), 'error' => ''];
    }

    public function replaceFile(int $id, array $upload): string
    {
        if ($this->failWith !== '') {
            return $this->failWith;
        }

        // Il nome base resta, cambia solo l'estensione.
        $base = pathinfo($this->rows[$id]['file'], PATHINFO_FILENAME) ?: $this->rows[$id]['slug'];
        $this->rows[$id]['file'] = $base.'.'.MediaTypes::extension(MediaUpload::fileName($upload));

        return '';
    }

    public function setAlt(int $id, string $alt): void
    {
        $this->rows[$id]['alt'] = $alt;
    }

    public function search(string $type, string $term, int $limit, int $offset): array
    {
        $found = [];

        foreach (array_reverse($this->rows, true) as $row) {
            if ($row['type'] !== $type || !empty($row['deleted'])) {
                continue;
            }

            if ($term === '' || stripos($row['name'].' '.$row['slug'].' '.$row['alt'], $term) !== false) {
                $found[] = $row;
            }
        }

        return ['rows' => array_slice($found, $offset, $limit), 'total' => count($found)];
    }
}
