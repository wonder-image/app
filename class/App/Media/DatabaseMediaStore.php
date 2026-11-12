<?php

namespace Wonder\App\Media;

use mysqli;
use mysqli_stmt;
use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Models\Media\Media;
use Wonder\App\Resources\Media\MediaResource;
use Wonder\App\Support\MediaFileManager;
use Wonder\App\Table;
use Wonder\Sql\Connection;

/**
 * I media nella tabella `media`. Le letture sono prepared statement; le
 * scritture di un file passano dalla Resource del tipo, con gli stessi
 * passi della sua pagina e dell'upload di massa (`mutateRequestValues()`,
 * `prepareFor()` e quindi `uploadFiles()`), così controlli, nome del file,
 * svg ripulito e coda delle misure sono uno solo.
 */
final class DatabaseMediaStore implements MediaStore
{
    private const COLUMNS = '`id`, `type`, `slug`, `name`, `alt`, `file`';

    private ?mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection;
    }

    public function rows(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $found = $this->execute(
            'SELECT '.self::COLUMNS.' FROM `'.Media::$table."` WHERE `deleted` = 'false' AND `id` IN ("
                .implode(', ', array_fill(0, count($ids), '?')).')',
            $ids
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        $rows = [];

        foreach ($found as $row) {
            $rows[(int) $row['id']] = self::row($row);
        }

        return $rows;
    }

    public function slugTaken(string $slug): bool
    {
        $row = $this->execute(
            'SELECT 1 FROM `'.Media::$table."` WHERE `deleted` = 'false' AND `slug` = ? LIMIT 1",
            [$slug]
        )->get_result()->fetch_row();

        return $row !== null && $row !== false;
    }

    public function create(string $type, array $values, array $upload): array
    {
        $result = $this->save(MediaResource::forType($type), [
            'name' => (string) $values['name'],
            'slug' => (string) $values['slug'],
            'alt' => (string) $values['alt'],
            'file' => $upload,
        ], null);

        return ['id' => (int) ($result['id'] ?? 0), 'error' => $result['error']];
    }

    public function replaceFile(int $id, array $upload): string
    {
        $old = Media::findById($id);

        if (!is_array($old) || $old === [] || !MediaTypes::has((string) ($old['type'] ?? ''))) {
            return MediaInputs::MISSING;
        }

        return $this->save(MediaResource::forType((string) $old['type']), ['file' => $upload], $old)['error'];
    }

    public function setAlt(int $id, string $alt): void
    {
        $this->execute('UPDATE `'.Media::$table.'` SET `alt` = ? WHERE `id` = ?', [self::stored($alt), $id]);
    }

    /**
     * Il testo come lo salva `sanitize()` dai form, senza l'escape SQL:
     * qui lo fa il prepared statement.
     */
    private static function stored(string $text): string
    {
        $text = trim($text);

        if ($text !== '' && function_exists('encodeConfiguredCharacters')) {
            $characters = $GLOBALS['CHARACTERS'] ?? null;
            $text = encodeConfiguredCharacters($text, is_array($characters) ? $characters : []);
        }

        return $text !== '' && function_exists('encodeUnsupportedUnicodeChars')
            ? encodeUnsupportedUnicodeChars($text)
            : $text;
    }

    public function search(string $type, string $term, int $limit, int $offset): array
    {
        $where = "`deleted` = 'false' AND `type` = ?";
        $parameters = [$type];

        if ($term !== '') {
            // `%` e `_` scritti da chi cerca sono testo, non caratteri jolly.
            $like = '%'.addcslashes($term, '\\%_').'%';
            $where .= ' AND (`name` LIKE ? OR `slug` LIKE ? OR `alt` LIKE ?)';
            array_push($parameters, $like, $like, $like);
        }

        $total = (int) ($this->execute('SELECT COUNT(*) FROM `'.Media::$table.'` WHERE '.$where, $parameters)
            ->get_result()->fetch_row()[0] ?? 0);

        $found = $this->execute(
            'SELECT '.self::COLUMNS.' FROM `'.Media::$table.'` WHERE '.$where.' ORDER BY `id` DESC LIMIT ? OFFSET ?',
            [...$parameters, max(1, $limit), max(0, $offset)]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['rows' => array_map(self::row(...), $found), 'total' => $total];
    }

    /**
     * Crea (`$old` null) o aggiorna un media come fa la sua Resource. Il
     * codice d'errore globale e la cartella corrente tornano com'erano:
     * chi sta salvando il record proprietario non deve accorgersene.
     *
     * @param class-string<MediaResource> $resourceClass
     * @return array{id: int, error: string}
     */
    private function save(string $resourceClass, array $request, ?array $old): array
    {
        $previousAlert = $GLOBALS['ALERT'] ?? '';
        $previousName = LegacyGlobals::get('NAME');
        $table = $resourceClass::modelTable();
        $schema = $resourceClass::prepareSchemaName();

        try {
            $GLOBALS['ALERT'] = '';
            LegacyGlobals::set('NAME', (object) [
                'table' => $table,
                'folder' => $resourceClass::legacyFolder(),
                'schema' => $schema,
            ]);

            $values = $resourceClass::mutateRequestValues($request, $old === null ? 'store' : 'update', 'backend', $old);
            $values = Table::key(Table::has($schema) ? $schema : $table)->prepareFor($table, $values, $old);
            $alert = $GLOBALS['ALERT'] ?? '';

            if (!empty($alert)) {
                return ['id' => 0, 'error' => self::alertText($alert)];
            }

            if (MediaFileManager::decodeStoredFiles($values['file'] ?? null) === []) {
                return ['id' => 0, 'error' => MediaInputs::UPLOAD_FAILED];
            }

            $query = $resourceClass::modelClass()::query();
            $result = $old === null
                ? $query->Insert($table, $values)
                : $query->Update($table, $values, 'id', (int) $old['id']);

            if (empty($result->success)) {
                return ['id' => 0, 'error' => MediaInputs::UPLOAD_FAILED];
            }

            $id = $old === null ? (int) ($result->insert_id ?? 0) : (int) $old['id'];

            return $id > 0 ? ['id' => $id, 'error' => ''] : ['id' => 0, 'error' => MediaInputs::UPLOAD_FAILED];
        } catch (Throwable $exception) {
            if (function_exists('__log')) {
                \__log($exception, 'media', 'save');
            }

            return ['id' => 0, 'error' => MediaInputs::UPLOAD_FAILED];
        } finally {
            $GLOBALS['ALERT'] = $previousAlert;
            LegacyGlobals::set('NAME', $previousName);
        }
    }

    /** Il testo di un codice di `notifications.json`, senza tag. */
    private static function alertText(mixed $alert): string
    {
        if (is_numeric($alert) && function_exists('__t')) {
            $text = trim(strip_tags((string) \__t('notifications.'.(int) $alert.'.text')));

            if ($text !== '' && !str_starts_with($text, 'notifications.')) {
                return $text;
            }
        }

        return is_string($alert) && !is_numeric($alert) && trim($alert) !== ''
            ? trim(strip_tags($alert))
            : MediaInputs::UPLOAD_FAILED;
    }

    /** @return array{id: int, type: string, slug: string, name: string, alt: string, file: string} */
    private static function row(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => (string) ($row['type'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'alt' => (string) ($row['alt'] ?? ''),
            'file' => (string) (MediaFileManager::decodeStoredFiles($row['file'] ?? null)[0] ?? ''),
        ];
    }

    /** La connessione si apre alla prima operazione, non alla costruzione. */
    private function connection(): mysqli
    {
        return $this->connection ??= ($GLOBALS['mysqli'] ?? null) ?? Connection::Connect('main');
    }

    private function execute(string $sql, array $parameters): mysqli_stmt
    {
        try {
            $statement = $this->connection()->prepare($sql);

            if ($statement && $statement->execute($parameters)) {
                return $statement;
            }
        } catch (Throwable) {
        }

        throw new RuntimeException('La libreria Media non risponde.');
    }
}
