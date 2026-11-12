<?php

namespace Wonder\App\Resources\Media;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\ResizeQueue;
use Wonder\App\Models\Media\Media;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\Formatters\String\SlugFormatter;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Form;

/**
 * Base delle sezioni della libreria Media (immagini, icone, documenti,
 * video): stessa tabella `media`, una Resource per tipo. Qui stanno form,
 * elenco, API e permessi comuni; ogni tipo dichiara percorso, testi, voce
 * di menu e `mediaType()`. Estensioni, cartella e peso arrivano da
 * `MediaTypes`.
 */
abstract class MediaResource extends Resource
{
    public static string $model = Media::class;

    /** La Resource di ogni tipo: chi crea o sostituisce un media passa da qui. */
    private const TYPE_RESOURCES = [
        MediaTypes::IMAGE => ImageResource::class,
        MediaTypes::ICON => IconResource::class,
        MediaTypes::DOCUMENT => DocumentResource::class,
        MediaTypes::VIDEO => VideoResource::class,
    ];

    abstract protected static function mediaType(): string;

    /**
     * La Resource che valida e salva i media di `$type`.
     *
     * @return class-string<MediaResource>
     */
    public static function forType(string $type): string
    {
        return self::TYPE_RESOURCES[$type]
            ?? throw new InvalidArgumentException("Tipo di media sconosciuto: {$type}");
    }

    /** Vero se il tipo ha il testo alternativo nel form e nelle API. */
    protected static function hasAlt(): bool
    {
        return false;
    }

    /** Vero se l'elenco mostra l'anteprima del file. */
    protected static function hasPreview(): bool
    {
        return true;
    }

    public static function legacyFolder(): string
    {
        return MediaTypes::folder(static::mediaType());
    }

    public static function formSchema(): array
    {
        $type = static::mediaType();
        $prepare = [
            'extensions' => MediaTypes::extensions($type),
            'max_size' => MediaTypes::maxSize($type),
        ];

        if (MediaTypes::resizes($type)) {
            $prepare['webp'] = RESPONSIVE_IMAGE_WEBP;
            $prepare['resize'] = RESPONSIVE_IMAGE_SIZES;
        }

        return array_values(array_filter([
            FormField::key('file')
                ->fileDragDrop(MediaTypes::accept($type))
                ->storeAs('{slug}')
                ->prepare($prepare)
                ->required(),
            FormField::key('name')->text()->required(),
            static::hasAlt() ? FormField::key('alt')->text() : null,
            // Solo da leggere: lo slug nasce dal nome e non cambia
            // (`mutateRequestValues()` ignora quello inviato in modifica).
            FormField::key('slug')->text()
                ->readonly()
                ->placeholder('Si crea dal nome al primo salvataggio'),
        ]));
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('file')->columnSpan(4),
                (new Container)->components(array_values(array_filter([
                    static::getInput('name'),
                    static::hasAlt() ? static::getInput('alt') : null,
                    static::getInput('slug'),
                ])))->columns(1)->columnSpan(8),
            ])->columns(12)->columnSpan(9),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return array_values(array_filter([
            static::hasPreview() ? TableColumn::key('file')->image()->link('edit')->size('little') : null,
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('slug')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ]));
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        $table = static::modelTable();

        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters()
            ->filterQuery(
                'Utilizzo',
                'usage',
                [
                    '' => ucfirst((string) static::getText('all')),
                    'used' => ucfirst((string) static::getText('full')),
                    'unused' => ucfirst((string) static::getText('empty')),
                ],
                static fn (array $values): string => MediaUsages::filterCondition($values, $table),
                'radio'
            );
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['view']);
    }

    public static function apiSchema(): ApiSchema
    {
        $read = static::hasAlt()
            ? ['id', 'name', 'slug', 'alt', 'type', 'file']
            : ['id', 'name', 'slug', 'type', 'file'];
        $write = static::hasAlt() ? ['name', 'alt', 'file'] : ['name', 'file'];

        return ApiSchema::for(static::class)
            ->fields('index', $read)
            ->fields('show', $read)
            ->fields('store', $write)
            ->fields('update', $write);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin'])
            ->apiCrud(['admin']);
    }

    public static function findStoreExistingValues(array $requestValues, string $context = 'backend'): ?array
    {
        $name = trim((string) ($requestValues['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $slug = function_exists('create_link')
            ? create_link($name)
            : (string) SlugFormatter::format($name);

        if ($slug === '') {
            return null;
        }

        $existing = static::modelClass()::find([
            'slug' => $slug,
            'type' => static::mediaType(),
            'deleted' => 'false',
        ], 1);

        return is_array($existing) && $existing !== [] ? $existing : null;
    }

    /**
     * Lo slug nasce con il media e non cambia più: con lui restano il nome
     * del file e quindi il suo indirizzo, anche quando il file viene
     * sostituito o il media rinominato. In creazione viene dal nome, o
     * dallo slug dato da chi crea il media dal campo di un form
     * (`MediaInputs`); l'unicità la garantisce il Model.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $oldSlug = trim((string) ($oldValues['slug'] ?? ''));

        if ($action === 'update' && $oldSlug !== '') {
            $values['slug'] = $oldSlug;
        } else {
            $slug = is_scalar($values['slug'] ?? null) ? trim((string) $values['slug']) : '';

            if ($slug !== '') {
                $values['slug'] = $slug;
            } elseif (!empty($values['name'])) {
                $values['slug'] = (string) $values['name'];
            } else {
                unset($values['slug']);
            }
        }

        $values['type'] = static::mediaType();

        return $values;
    }

    /**
     * Un media usato da qualche record non si elimina: il messaggio dice
     * dove. Vale per la pagina, le API e l'elenco.
     */
    public static function assertDeletable(int|string $id): void
    {
        $where = MediaUsages::message($id);

        if ($where !== '') {
            throw new RuntimeException(static::forRow($id)::inUseMessage($where));
        }
    }

    /**
     * I quattro tipi stanno nella stessa tabella: l'elenco risolve la
     * Resource dalla tabella e può prendere quella di un altro tipo.
     * Cartella, formato e testi vanno presi dal tipo della riga.
     *
     * @param int|string|array<string, mixed> $row
     * @return class-string<MediaResource>
     */
    protected static function forRow(int|string|array $row): string
    {
        if (!is_array($row)) {
            try {
                $query = sqlSelect('media', ['id' => $row], 1);
                $row = ($query->exists ?? false) ? (array) $query->row : [];
            } catch (Throwable) {
                $row = [];
            }
        }

        return self::TYPE_RESOURCES[(string) ($row['type'] ?? '')] ?? static::class;
    }

    /**
     * Il file di un media in uso non si toglie dall'elenco: resterebbe un
     * media senza file dovunque è usato. Si sostituisce dalla scheda.
     */
    public static function assertFileDeletable(int|string $id, string $column): void
    {
        static::assertDeletable($id);
    }

    /**
     * Avvisi nella scheda: per le immagini lo stato delle misure, poi dove
     * è usato il media, se lo è.
     */
    public static function formNotices(array $values, string $mode): array
    {
        $id = $values['id'] ?? null;

        if ($mode !== 'edit' || !(is_int($id) || is_string($id))) {
            return [];
        }

        $notices = static::resizeNotices($values);

        try {
            $where = MediaUsages::message($id);
        } catch (Throwable) {
            // Registro non ancora creato (prima di `forge update`): niente avviso.
            $where = '';
        }

        if ($where !== '') {
            $notices[] = ['level' => 'info', 'message' => $where];
        }

        return $notices;
    }

    /**
     * Lo stato delle misure responsive dei file del media, letto dalla coda
     * (`ResizeQueue`). Solo per i tipi che le generano.
     *
     * @return list<array{level: string, message: string}>
     */
    public static function resizeNotices(array $values, ?string $root = null): array
    {
        $root ??= defined('ROOT') ? (string) ROOT : null;

        if ($root === null || !MediaTypes::resizes(static::mediaType())) {
            return [];
        }

        $directory = rtrim($root, '/').'/'.ResizeQueue::UPLOAD.trim(static::legacyFolder(), '/').'/';
        $notices = [];

        try {
            foreach (MediaFileManager::decodeStoredFiles($values['file'] ?? null) as $file) {
                $status = ResizeQueue::status($directory.$file);

                if ($status === ResizeQueue::PENDING) {
                    $notices[] = [
                        'level' => 'info',
                        'message' => 'Le misure di questa immagine sono in preparazione: finché non sono pronte il sito mostra il file originale.',
                    ];
                } elseif ($status === ResizeQueue::FAILED) {
                    $error = trim((string) ResizeQueue::error($directory.$file));
                    $notices[] = [
                        'level' => 'warning',
                        'message' => 'Le misure di questa immagine non si sono generate: il sito mostra il file originale. Carica di nuovo il file per riprovare.'
                            .($error !== '' ? "\nMotivo: ".$error : ''),
                    ];
                }
            }
        } catch (Throwable) {
            // Coda non ancora creata (prima di `forge update`): niente avviso.
            return [];
        }

        return $notices;
    }

    /** Un media eliminato dall'elenco perde riga e file, come dalla scheda. */
    public static function listDeletesRecord(): bool
    {
        return true;
    }

    public static function afterDelete(int|string $id, object $result, array $values = []): void
    {
        if (empty($result->success) || empty($values['file'])) {
            return;
        }

        $resource = static::forRow($values);

        MediaFileManager::deleteFiles(
            ROOT.'/assets/upload/'.trim($resource::legacyFolder(), '/'),
            $resource::fileCleanupFormat(),
            MediaFileManager::decodeStoredFiles($values['file'])
        );
    }

    protected static function inUseMessage(string $where): string
    {
        return ucfirst((string) static::getText('this')).' '.static::label()
            .' non si può eliminare. '.$where.' Togli prima questi utilizzi e riprova.';
    }

    protected static function fileCleanupFormat(): array
    {
        $input = static::getInput('file');
        $prepare = (array) ($input->get('prepare') ?? []);

        if (!isset($prepare['dir'])) {
            $prepare['dir'] = '/';
        }

        return $prepare;
    }
}
