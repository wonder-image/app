<?php

namespace Wonder\App\Media;

use Throwable;
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
            if ($field instanceof InputMedia) {
                $fields[(string) $name] = $field->mediaConfig();
            }
        }

        return new self($fields === [] ? '' : $resourceClass::modelTable(), $fields);
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
            if (is_string($name) && isset($this->fields[$name])) {
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
                $values[$name] = (string) ($request['current'] ?? '');
            }

            return $values;
        }

        foreach ($requests as $name => $request) {
            $values[$name] = $this->decide($name, $this->fields[$name], $request, $rows, $values, $oldValues);
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
        foreach ($this->alts as $mediaId => $alt) {
            try {
                MediaLibrary::store()->setAlt($mediaId, $alt);
            } catch (Throwable $exception) {
                self::log($exception, 'alt');
            }
        }

        $this->alts = [];

        foreach (array_keys($this->fields) as $name) {
            if (!array_key_exists($name, $values)) {
                continue;
            }

            try {
                MediaUsages::sync($this->table, $id, $name, [is_scalar($values[$name]) ? (string) $values[$name] : null]);
            } catch (Throwable $exception) {
                // Il record è già salvato: un registro che non risponde non lo annulla.
                self::log($exception, 'sync');
            }
        }
    }

    /** Il record è stato eliminato: non usa più nessun media. */
    public function forget(int|string $id): void
    {
        if ($this->fields === []) {
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

            $source = array_merge($oldValues ?? [], $values);
            $title = MediaUpload::title($upload);
            $slug = $config['slug'] !== '' ? self::fill($config['slug'], $source) : '';
            $created = $store->create($type, [
                'name' => $title !== '' ? $title : $slug,
                'slug' => self::uniqueSlug($slug !== '' ? $slug : $title, $store->slugTaken(...)),
                'alt' => $config['alt'] !== '' ? self::fill($config['alt'], $source) : '',
            ], $upload);
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
