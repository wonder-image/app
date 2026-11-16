<?php

namespace Wonder\App\Media;

use Throwable;
use Wonder\App\ResourceSchema\Inputs\InputFileDragDrop;
use Wonder\App\ResourceSchema\Inputs\InputMedia;
use Wonder\Data\Formatters\String\SlugFormatter;

/**
 * I campi media (`FormField::mediaImage()`, `mediaIcon()`, ...) di una
 * Resource al momento del salvataggio: la colonna del record tiene l'id di
 * un media della libreria.
 *
 * `resolve()` gira sui valori della richiesta, dopo
 * `mutateRequestValues()` e prima della preparazione: per ogni campo
 * trasforma ciò che è arrivato (l'id di un media scelto, un file caricato
 * o il campo svuotato) nell'id da scrivere. Un file caricato crea un media
 * del tipo giusto oppure, se il media di prima è usato solo da questa
 * colonna di questo record, ne sostituisce il file lasciando id, slug e
 * testo alternativo. Quello che non va (id sconosciuto, tipo sbagliato,
 * file rifiutato) finisce in `errors()` e ferma il salvataggio.
 *
 * `sync()` e `forget()` tengono aggiornato il registro `MediaUsages`.
 *
 * Una Resource senza campi media non fa niente: nessuna query.
 */
final class MediaInputs
{
    /** Suffisso del campo file del form: `<campo>__wi_media_upload`. */
    public const UPLOAD_SUFFIX = '__wi_media_upload';

    /** Campo drag & drop: l'ordine dei file (nomi salvati e posizioni dei caricati). */
    public const FILES_SUFFIX = '__wi_files';

    /** Campo drag & drop: per ogni file l'id del media scelto dalla libreria, o null. */
    public const PICKS_SUFFIX = '__wi_picks';

    /** Campo drag & drop: nome del file mostrato → id del media. */
    public const MAP_SUFFIX = '__wi_media';

    public const INVALID = 'Il media scelto non è valido.';
    public const MISSING = 'Il media scelto non è più in libreria: scegline un altro.';
    public const WRONG_TYPE = 'Il media scelto non è del tipo richiesto da questo campo.';
    public const UPLOAD_FAILED = 'Il file non è stato caricato: riprova.';
    public const UPLOAD_LOST = 'Il file non è arrivato: è troppo pesante o il caricamento si è interrotto.';

    /** @var array<string, string> campo => errore dell'ultimo `resolve()` */
    private array $errors = [];

    /** @var array<int, string> media => testo alternativo da scrivere a salvataggio riuscito */
    private array $alts = [];

    /**
     * @param array<string, array{type: string, slug: string, alt: string}> $fields campo => configurazione
     */
    public function __construct(private readonly string $table, private readonly array $fields)
    {
    }

    /**
     * I campi media dichiarati nel `formSchema()` della Resource.
     *
     * @param class-string<\Wonder\App\Resource> $resourceClass
     */
    public static function for(string $resourceClass): self
    {
        $fields = [];

        foreach ($resourceClass::safeFormFieldsByKey() as $name => $field) {
            if ($field instanceof InputMedia || ($field instanceof InputFileDragDrop && $field->isMedia())) {
                $fields[(string) $name] = $field->mediaConfig();
            }
        }

        // Anche i soli media nelle righe dei repeater: alla sua eliminazione il padre li libera.
        $table = $fields !== [] || $resourceClass::hasRepeaterMedia() ? $resourceClass::modelTable() : '';

        return new self($table, $fields);
    }

    /**
     * Il nome di un campo di servizio: `logo` → `logo__wi_files`,
     * `values[logo]` → `values[logo__wi_files]`.
     */
    public static function fieldName(string $name, string $suffix): string
    {
        $name = (string) preg_replace('/\[\]$/', '', $name);

        return str_ends_with($name, ']') ? substr($name, 0, -1).$suffix.']' : $name.$suffix;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /**
     * Le chiavi di caricamento (`<campo>__wi_media_upload`) dei campi media
     * tra quelli dati: una pagina in sola lettura che lascia modificare il
     * campo deve lasciar passare anche il suo file.
     *
     * @param list<string> $names
     * @return list<string>
     */
    public function uploadKeys(array $names): array
    {
        $keys = [];

        foreach ($names as $name) {
            if (!is_string($name) || !isset($this->fields[$name])) {
                continue;
            }

            if (self::isFiles($this->fields[$name])) {
                array_push($keys, $name.self::FILES_SUFFIX, $name.self::PICKS_SUFFIX, $name.self::MAP_SUFFIX);
            } else {
                $keys[] = $name.self::UPLOAD_SUFFIX;
            }
        }

        return $keys;
    }

    /** @return array<string, string> campo => errore dell'ultimo `resolve()` */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * I valori della richiesta con l'id del media al posto di ciò che i
     * campi media hanno inviato. Un campo che non è nella richiesta resta
     * fuori: il record tiene il suo valore.
     *
     * @param array<string, mixed> $values valori della richiesta (`$_POST` + `$_FILES`)
     * @param array<string, mixed>|null $oldValues riga salvata, null in creazione
     * @return array<string, mixed>
     */
    public function resolve(array $values, ?array $oldValues = null): array
    {
        $this->errors = [];
        $this->alts = [];

        if ($this->fields === []) {
            return $values;
        }

        $requests = [];
        $ids = [];

        foreach ($this->fields as $name => $config) {
            if (self::isFiles($config)) {
                $request = $this->filesRequest($name, $values, $oldValues);
                unset($values[$name.self::FILES_SUFFIX], $values[$name.self::PICKS_SUFFIX], $values[$name.self::MAP_SUFFIX]);

                if ($request === null) {
                    if (is_array($values[$name] ?? null)) {
                        unset($values[$name]);
                    }

                    continue;
                }

                $requests[$name] = $request;
                array_push($ids, ...$request['ids']);

                continue;
            }

            $request = $this->request($name, $values, $oldValues);
            unset($values[$name.self::UPLOAD_SUFFIX]);

            if ($request === null) {
                // Niente da decidere: né l'id né un file. La voce vuota di
                // `$_FILES` con il nome della colonna non deve arrivare al record.
                if (is_array($values[$name] ?? null)) {
                    unset($values[$name]);
                }

                continue;
            }

            $requests[$name] = $request;
            array_push($ids, $request['current'], $request['posted']);
        }

        if ($requests === []) {
            return $values;
        }

        try {
            $rows = MediaLibrary::store()->rows(array_values(array_unique(array_filter($ids))));
        } catch (Throwable $exception) {
            self::log($exception, 'resolve');

            foreach ($requests as $name => $request) {
                $this->errors[$name] = self::UPLOAD_FAILED;
                $values[$name] = self::isFiles($this->fields[$name])
                    ? self::value($request['current'], $this->fields[$name])
                    : (string) ($request['current'] ?? '');
            }

            return $values;
        }

        foreach ($requests as $name => $request) {
            $values[$name] = self::isFiles($this->fields[$name])
                ? $this->decideFiles($name, $this->fields[$name], $request, $rows, $values, $oldValues)
                : $this->decide($name, $this->fields[$name], $request, $rows, $values, $oldValues);
        }

        return $values;
    }

    /**
     * Dopo un salvataggio riuscito: il registro degli utilizzi segue i
     * campi media presenti in `$values` (i valori scritti nel record).
     *
     * @param array<string, mixed> $values
     */
    public function sync(int|string $id, array $values): void
    {
        $this->commitAlts();

        foreach ($this->fields as $name => $config) {
            if (!array_key_exists($name, $values)) {
                continue;
            }

            $mediaIds = self::isFiles($config)
                ? InputFileDragDrop::mediaIds($values[$name])
                : [is_scalar($values[$name]) ? (string) $values[$name] : null];

            try {
                MediaUsages::sync($this->table, $id, $name, $mediaIds);
            } catch (Throwable $exception) {
                // Il record è già salvato: un registro che non risponde non lo annulla.
                self::log($exception, 'sync');
            }
        }
    }

    /**
     * Scrive i testi alternativi decisi dall'ultimo `resolve()`. `sync()` lo
     * fa da sé; le righe dei repeater lo chiamano riga per riga.
     */
    public function commitAlts(): void
    {
        foreach ($this->alts as $mediaId => $alt) {
            try {
                MediaLibrary::store()->setAlt($mediaId, $alt);
            } catch (Throwable $exception) {
                self::log($exception, 'alt');
            }
        }

        $this->alts = [];
    }

    /** Il record è stato eliminato: non usa più nessun media. */
    public function forget(int|string $id): void
    {
        if ($this->table === '') {
            return;
        }

        try {
            MediaUsages::forget($this->table, $id);
        } catch (Throwable $exception) {
            self::log($exception, 'forget');
        }
    }

    /**
     * Riempie un modello (`{slug}`, `{name} {color}`) con i valori del
     * record, come testo semplice. Le chiavi sconosciute restano vuote.
     *
     * @param array<string, mixed> $values
     */
    public static function fill(string $template, array $values): string
    {
        $text = (string) preg_replace_callback(
            '/\{([A-Za-z0-9_]+)\}/',
            static fn (array $match): string => is_scalar($values[$match[1]] ?? null)
                ? MediaFile::plain((string) $values[$match[1]])
                : '',
            $template
        );

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Lo slug libero più vicino a `$text`: se è occupato prende `-2`,
     * `-3`, ... `$taken` dice se uno slug è già di un altro media.
     *
     * @param callable(string): bool $taken
     */
    public static function uniqueSlug(string $text, callable $taken): string
    {
        $base = self::slug($text);
        $base = $base !== '' ? $base : 'media';
        $slug = $base;

        for ($suffix = 2; $taken($slug); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private static function slug(string $text): string
    {
        $slug = function_exists('create_link')
            ? create_link($text)
            : (string) SlugFormatter::format($text);

        return trim(strtolower($slug), '-');
    }

    /**
     * Cosa ha inviato il campo: null se non ha inviato niente.
     *
     * @return array{upload: ?array, lost: bool, posted: ?int, raw: string, current: ?int}|null
     */
    private function request(string $name, array $values, ?array $oldValues): ?array
    {
        $separate = $values[$name.self::UPLOAD_SUFFIX] ?? null;
        $own = $values[$name] ?? null;
        $upload = MediaUpload::from($separate) ?? MediaUpload::from($own);
        $lost = $upload === null && (MediaUpload::lost($separate) || MediaUpload::lost($own));
        $sent = array_key_exists($name, $values) && !is_array($own);

        if ($upload === null && !$lost && !$sent) {
            return null;
        }

        $raw = $sent && is_scalar($own) ? trim((string) $own) : '';

        return [
            'upload' => $upload,
            'lost' => $lost,
            'posted' => self::id($raw),
            'raw' => $raw,
            'current' => self::id($oldValues[$name] ?? null),
        ];
    }

    /**
     * L'id da scrivere nella colonna, come stringa (`''` = nessun media).
     *
     * @param array{type: string, slug: string, alt: string} $config
     * @param array{upload: ?array, lost: bool, posted: ?int, raw: string, current: ?int} $request
     * @param array<int, array<string, mixed>> $rows i media già letti, per id
     */
    private function decide(string $name, array $config, array $request, array $rows, array $values, ?array $oldValues): string
    {
        $current = $request['current'];
        $unchanged = (string) ($current ?? '');

        if ($request['lost']) {
            $this->errors[$name] = self::UPLOAD_LOST;

            return $unchanged;
        }

        if ($request['upload'] !== null) {
            return $this->upload($name, $config, $request['upload'], $current, $rows, $values, $oldValues);
        }

        if ($request['raw'] === '') {
            return '';
        }

        $id = $request['posted'];
        $row = $id !== null ? ($rows[$id] ?? null) : null;

        if ($id === null || $row === null || $row['type'] !== $config['type']) {
            $this->errors[$name] = $id === null ? self::INVALID : ($row === null ? self::MISSING : self::WRONG_TYPE);

            return $unchanged;
        }

        $this->followAlt($config, $row, $values, $oldValues);

        return (string) $id;
    }

    private function upload(string $name, array $config, array $upload, ?int $current, array $rows, array $values, ?array $oldValues): string
    {
        $unchanged = (string) ($current ?? '');
        $type = $config['type'];
        $store = MediaLibrary::store();

        if (!MediaTypes::accepts($type, MediaUpload::fileName($upload))) {
            $this->errors[$name] = 'Il file deve essere di tipo '.implode(', ', MediaTypes::extensions($type)).'.';

            return $unchanged;
        }

        $row = $current !== null ? ($rows[$current] ?? null) : null;

        try {
            // Media usato solo qui: cambia il file, restano id, slug e testo alternativo.
            if ($row !== null && $row['type'] === $type && $this->exclusive($current, $name, $oldValues['id'] ?? null)) {
                $error = $store->replaceFile($current, $upload);

                if ($error !== '') {
                    $this->errors[$name] = $error;

                    return $unchanged;
                }

                $this->followAlt($config, $row, $values, $oldValues);

                return $unchanged;
            }

            $created = $this->create($type, $config, $upload, $values, $oldValues);
        } catch (Throwable $exception) {
            self::log($exception, 'upload');
            $created = ['id' => 0, 'error' => self::UPLOAD_FAILED];
        }

        if ($created['id'] <= 0) {
            $this->errors[$name] = $created['error'] !== '' ? $created['error'] : self::UPLOAD_FAILED;

            return $unchanged;
        }

        return (string) $created['id'];
    }

    /** Un media nuovo dal file caricato, con slug e testo alternativo dai modelli. */
    private function create(string $type, array $config, array $upload, array $values, ?array $oldValues): array
    {
        $store = MediaLibrary::store();
        $source = array_merge($oldValues ?? [], $values);
        $title = MediaUpload::title($upload);
        $slug = $config['slug'] !== '' ? self::fill($config['slug'], $source) : '';

        return $store->create($type, [
            'name' => $title !== '' ? $title : $slug,
            'slug' => self::uniqueSlug($slug !== '' ? $slug : $title, $store->slugTaken(...)),
            'alt' => $config['alt'] !== '' ? self::fill($config['alt'], $source) : '',
            'source' => $this->table,
        ], $upload);
    }

    /** Vero per un campo drag & drop (`fileDragDrop()->mediaLibrary()`). */
    private static function isFiles(array $config): bool
    {
        return ($config['input'] ?? '') === 'files';
    }

    /**
     * Cosa ha inviato un campo drag & drop, file per file e in ordine:
     * null se non ha inviato niente.
     *
     * Dal form arriva l'elenco `<campo>__wi_files`: i nomi dei file già
     * salvati (tradotti in id da `<campo>__wi_media`) e la posizione dei
     * file nuovi in `$_FILES`; `<campo>__wi_picks` dice quali file nuovi
     * sono media scelti dalla libreria. Dalle API arriva un id, una lista
     * di id (anche JSON) oppure solo file.
     *
     * @return array{entries: list<array<string, mixed>>, ids: list<int>, current: list<int>}|null
     */
    private function filesRequest(string $name, array $values, ?array $oldValues): ?array
    {
        $current = InputFileDragDrop::mediaIds($oldValues[$name] ?? null);
        $own = $values[$name] ?? null;
        $isUpload = is_array($own) && isset($own['error']);
        $uploads = $isUpload ? MediaUpload::split($own) : [];
        $entries = [];

        if (array_key_exists($name.self::FILES_SUFFIX, $values)) {
            $manifest = self::jsonList($values[$name.self::FILES_SUFFIX]);
            $picks = self::jsonList($values[$name.self::PICKS_SUFFIX] ?? null);
            $map = is_string($values[$name.self::MAP_SUFFIX] ?? null) ? json_decode($values[$name.self::MAP_SUFFIX], true) : null;
            $map = is_array($map) ? $map : [];
            // I file ancora in caricamento lato client non hanno una scelta: le scelte sono le ultime.
            $offset = count($manifest) - count($picks);

            foreach ($manifest as $index => $entry) {
                if (is_int($entry)) {
                    $pick = $index >= $offset ? self::id($picks[$index - $offset] ?? null) : null;

                    if ($pick !== null) {
                        // Il file inviato è solo l'anteprima del media scelto.
                        $entries[] = ['kind' => 'pick', 'id' => $pick];
                    } elseif (is_array($uploads[$entry] ?? null)) {
                        $entries[] = ['kind' => 'upload', 'upload' => $uploads[$entry]];
                    } else {
                        $entries[] = ['kind' => 'lost'];
                    }

                    continue;
                }

                if (!is_string($entry) || trim($entry) === '') {
                    continue;
                }

                if (str_starts_with($entry, 'media:')) {
                    $entries[] = self::pickEntry(substr($entry, 6));

                    continue;
                }

                $entries[] = ['kind' => 'name', 'name' => $entry, 'id' => self::id($map[$entry] ?? null)];
            }
        } elseif ($isUpload) {
            if ($uploads === []) {
                return null;
            }

            foreach ($uploads as $upload) {
                $entries[] = $upload === false ? ['kind' => 'lost'] : ['kind' => 'upload', 'upload' => $upload];
            }
        } elseif (array_key_exists($name, $values)) {
            $raw = is_string($own) ? trim($own) : $own;

            if (is_string($raw) && str_starts_with($raw, '[')) {
                $raw = json_decode($raw, true);
                $raw = is_array($raw) ? $raw : [null];
            }

            foreach ($raw === '' || $raw === null ? [] : (is_array($raw) ? $raw : [$raw]) as $item) {
                $item = is_string($item) && str_starts_with(trim($item), 'media:') ? substr(trim($item), 6) : $item;
                $entries[] = self::pickEntry($item);
            }
        } else {
            return null;
        }

        $ids = $current;

        foreach ($entries as $entry) {
            if (($entry['id'] ?? null) !== null) {
                $ids[] = $entry['id'];
            }
        }

        return ['entries' => $entries, 'ids' => $ids, 'current' => $current];
    }

    /**
     * Il valore della colonna di un campo drag & drop: l'id, o la lista
     * JSON degli id con più file (`''` = nessun media).
     *
     * @param array<string, mixed> $config
     * @param array{entries: list<array<string, mixed>>, ids: list<int>, current: list<int>} $request
     * @param array<int, array<string, mixed>> $rows
     */
    private function decideFiles(string $name, array $config, array $request, array $rows, array $values, ?array $oldValues): string
    {
        $types = (array) $config['types'];
        $current = $request['current'];
        $result = [];
        $error = '';

        foreach ($request['entries'] as $entry) {
            $id = null;

            switch ($entry['kind']) {
                case 'lost':
                    $error = $error !== '' ? $error : self::UPLOAD_LOST;
                    break;

                case 'invalid':
                    $error = $error !== '' ? $error : self::INVALID;
                    break;

                case 'pick':
                    $row = $rows[$entry['id']] ?? null;

                    if ($row === null || !in_array($row['type'], $types, true)) {
                        $error = $error !== '' ? $error : ($row === null ? self::MISSING : self::WRONG_TYPE);
                    } elseif (!self::allowedExtension($config, (string) ($row['file'] ?? ''))) {
                        $error = $error !== '' ? $error : self::extensionError($config);
                    } else {
                        $id = $entry['id'];
                    }

                    break;

                case 'name':
                    $id = self::storedId($entry, $types, $current, $rows);
                    break;

                case 'upload':
                    $id = $this->uploadFile($name, $config, $entry['upload'], $current, $rows, $values, $oldValues, $error);
                    break;
            }

            if ($id !== null && !in_array($id, $result, true)) {
                $result[] = $id;
            }
        }

        if ($error !== '') {
            $this->errors[$name] = $error;

            if (!$config['multiple']) {
                return self::value($current, $config);
            }
        }

        if (!$config['multiple'] && count($result) > 1) {
            $result = [end($result)];
        }

        foreach ($result as $id) {
            if (isset($rows[$id])) {
                $this->followAlt($config, $rows[$id], $values, $oldValues);
            }
        }

        return self::value($result, $config);
    }

    /**
     * L'id di un file già salvato, dal suo nome: vale solo se il media
     * esiste, è di un tipo ammesso e ha proprio quel file. Un nome che non
     * torna con nessun media resta fuori.
     *
     * @param list<string> $types
     * @param list<int> $current
     */
    private static function storedId(array $entry, array $types, array $current, array $rows): ?int
    {
        $matches = static function (?array $row) use ($entry, $types): bool {
            if ($row === null || !in_array($row['type'], $types, true)) {
                return false;
            }

            $file = (string) ($row['file'] ?? '');

            return $file !== '' && in_array($entry['name'], [$file, MediaTypes::folder($row['type']).'/'.$file], true);
        };

        if ($entry['id'] !== null && $matches($rows[$entry['id']] ?? null)) {
            return $entry['id'];
        }

        foreach ($current as $id) {
            if ($matches($rows[$id] ?? null)) {
                return $id;
            }
        }

        return null;
    }

    /** Il file rispetta le estensioni del campo (nessuna = tutte quelle dei tipi). */
    private static function allowedExtension(array $config, string $file): bool
    {
        $only = (array) ($config['extensions'] ?? []);

        return $only === [] || in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $only, true);
    }

    private static function extensionError(array $config): string
    {
        $extensions = (array) ($config['extensions'] ?? []);

        if ($extensions === []) {
            $extensions = array_unique(array_merge(...array_map(MediaTypes::extensions(...), (array) $config['types'])));
        }

        return 'Il file deve essere di tipo '.implode(', ', $extensions).'.';
    }

    /**
     * Un file nuovo di un campo drag & drop: diventa un media del primo
     * tipo ammesso che lo accetta. Con un file solo, se il media di prima
     * è usato solo qui, ne cambia il file.
     *
     * @param list<int> $current
     */
    private function uploadFile(string $name, array $config, array $upload, array $current, array $rows, array $values, ?array $oldValues, string &$error): ?int
    {
        $types = (array) $config['types'];
        $type = null;

        foreach ($types as $candidate) {
            if (MediaTypes::accepts($candidate, MediaUpload::fileName($upload))) {
                $type = $candidate;
                break;
            }
        }

        if ($type === null || !self::allowedExtension($config, MediaUpload::fileName($upload))) {
            $error = $error !== '' ? $error : self::extensionError($config);

            return null;
        }

        $old = count($current) === 1 ? $current[0] : null;
        $row = $old !== null ? ($rows[$old] ?? null) : null;

        try {
            if (!$config['multiple'] && $row !== null && $row['type'] === $type && $this->exclusive($old, $name, $oldValues['id'] ?? null)) {
                $failure = MediaLibrary::store()->replaceFile($old, $upload);

                if ($failure !== '') {
                    $error = $error !== '' ? $error : $failure;

                    return null;
                }

                return $old;
            }

            $created = $this->create($type, $config, $upload, $values, $oldValues);
        } catch (Throwable $exception) {
            self::log($exception, 'upload');
            $created = ['id' => 0, 'error' => self::UPLOAD_FAILED];
        }

        if ($created['id'] <= 0) {
            $error = $error !== '' ? $error : ($created['error'] !== '' ? $created['error'] : self::UPLOAD_FAILED);

            return null;
        }

        return (int) $created['id'];
    }

    /** @param list<int> $ids */
    private static function value(array $ids, array $config): string
    {
        if ($ids === []) {
            return '';
        }

        return $config['multiple'] ? (string) json_encode(array_values($ids)) : (string) $ids[0];
    }

    /** @return array{kind: string, id?: int} */
    private static function pickEntry(mixed $value): array
    {
        $id = self::id(is_int($value) ? $value : (is_string($value) ? $value : null));

        return $id === null ? ['kind' => 'invalid'] : ['kind' => 'pick', 'id' => $id];
    }

    /** @return list<mixed> */
    private static function jsonList(mixed $value): array
    {
        $list = is_string($value) ? json_decode($value, true) : null;

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * Vero se il media è usato solo da questa colonna di questo record.
     * Senza una risposta dal registro vale no: si crea un media nuovo.
     */
    private function exclusive(int $mediaId, string $column, mixed $ownerId): bool
    {
        if (!is_scalar($ownerId) || trim((string) $ownerId) === '') {
            return false;
        }

        try {
            foreach (MediaUsages::owners($mediaId) as $owner) {
                if ($owner['table'] !== $this->table || $owner['id'] !== trim((string) $ownerId) || $owner['column'] !== $column) {
                    return false;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Il testo alternativo segue il record (prodotto rinominato) solo se
     * nessuno l'ha scritto a mano: è ancora quello che il modello dava con
     * i valori di prima.
     */
    private function followAlt(array $config, array $row, array $values, ?array $oldValues): void
    {
        if ($config['alt'] === '' || $oldValues === null) {
            return;
        }

        $before = self::fill($config['alt'], $oldValues);
        $after = self::fill($config['alt'], array_merge($oldValues, $values));

        if ($before === '' || $after === $before || MediaFile::plain((string) $row['alt']) !== $before) {
            return;
        }

        // Si scrive in `sync()`: se il record non si salva, resta com'era.
        $this->alts[(int) $row['id']] = $after;
    }

    private static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || preg_match('/^\d+$/D', trim($value)) !== 1) {
            return null;
        }

        return (int) trim($value) > 0 ? (int) trim($value) : null;
    }

    private static function log(Throwable $exception, string $action): void
    {
        if (function_exists('__log')) {
            \__log($exception, 'media', $action);
        }
    }
}
