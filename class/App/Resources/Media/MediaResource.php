<?php

namespace Wonder\App\Resources\Media;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wonder\App\Media\MediaDownloads;
use Wonder\App\Media\MediaFile;
use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\ResizeQueue;
use Wonder\App\Models\Media\Media;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\FromValues;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\Formatters\String\SlugFormatter;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Dropdown;
use Wonder\Elements\Components\RichText;
use Wonder\Elements\Components\SectionTitle;
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
            FromValues::make(static fn (array $values, string $mode): ?Card => static::generatedFilesCard($values, $mode)),
        ])->columns(12);
    }

    /**
     * La card «File generati» della scheda: l'originale e le misure che il
     * ridimensionatore ha creato, ognuno da scaricare, più lo zip di tutti.
     */
    public static function generatedFilesCard(array $values, string $mode, ?string $root = null): ?Card
    {
        $file = MediaFileManager::decodeStoredFiles($values['file'] ?? null)[0] ?? '';

        if ($mode !== 'edit' || $file === '' || (int) ($values['id'] ?? 0) <= 0) {
            return null;
        }

        $media = new MediaFile(
            (int) $values['id'],
            static::mediaType(),
            (string) ($values['slug'] ?? ''),
            (string) ($values['name'] ?? ''),
            (string) ($values['alt'] ?? ''),
            $file,
        );
        $downloads = MediaDownloads::of($media, $root);

        if ($downloads['groups'] === []) {
            return null;
        }

        $components = [SectionTitle::make('File generati')->level(5)];

        if ($downloads['pending']) {
            $components[] = RichText::make('<p class="small text-body-secondary mb-0"><i class="bi bi-hourglass-split me-1"></i>In preparazione: le misure arrivano entro un minuto.</p>');
        } elseif ($downloads['error'] !== null) {
            $components[] = RichText::make('<p class="small text-warning-emphasis mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Le misure non si sono generate: carica di nuovo il file per riprovare.</p>');
        }

        $rows = '';

        foreach ($downloads['groups'] as $group) {
            $rows .= static::downloadRow($group);
        }

        $components[] = RichText::make('<ul class="list-group list-group-flush">'.$rows.'</ul>')->tag('div');
        $files = MediaDownloads::files($downloads);

        if (count($files) > 1 && class_exists('ZipArchive') && function_exists('__r')) {
            $components[] = Button::make('Scarica tutto (.zip)', __r('backend.media.zip', ['id' => $media->id]))
                ->icon('bi bi-file-earmark-zip', 'start')
                ->outline()
                ->size('sm')
                ->block();
        }

        return (new Card)->components($components)->columns(1)->columnSpan(3);
    }

    /** Una riga della card: misura, formati e peso, e il bottone per scaricarla. */
    private static function downloadRow(array $group): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $first = $group['files'][0];
        $size = $first['width'] !== null && $first['height'] !== null
            ? $first['width'].' × '.$first['height'].' px'
            : null;
        $meta = implode(' · ', array_filter([
            implode(', ', array_map(static fn (array $file): string => $file['format'], $group['files'])),
            $size,
        ]));

        if (count($group['files']) === 1) {
            $action = Button::make('', $first['url'])
                ->icon('bi bi-download', 'start')
                ->title('Scarica '.$first['name'].' ('.MediaDownloads::size($first['bytes']).')')
                ->attr('aria-label', 'Scarica '.$first['name'])
                ->download($first['name'])
                ->outline()
                ->size('sm')
                ->schema('inline', true)
                ->render('bootstrap');
            $meta .= ' · '.MediaDownloads::size($first['bytes']);
        } else {
            $dropdown = Dropdown::make('Scarica')->outline()->size('sm')->align('end');

            foreach ($group['files'] as $file) {
                $dropdown->item($file['format'].' · '.MediaDownloads::size($file['bytes']), $file['url'], [
                    'icon' => 'bi bi-download',
                    'attributes' => ['download' => $file['name'], 'title' => $file['name']],
                ]);
            }

            $action = $dropdown->render('bootstrap');
        }

        return '<li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0">'
            .'<div class="min-w-0"><div class="fw-semibold">'.$escape($group['label']).'</div>'
            .'<div class="small text-body-secondary text-truncate">'.$escape($meta).'</div></div>'
            .'<div class="flex-shrink-0">'.$action.'</div>'
            .'</li>';
    }

    public static function tableSchema(): array
    {
        return array_values(array_filter([
            static::hasPreview() ? TableColumn::key('file')->image()->link('edit')->size('little') : null,
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('slug')->text(),
            // Calcolata da `tableLayoutSchema()->select()`: le sezioni che usano il media.
            TableColumn::key('usage_tables')->text()->formatter(static fn (array $row): string => static::usageBadges($row['usage_tables'] ?? null)),
            TableColumn::key('actions')->button()->actions([
                'edit',
                'original' => ['label' => 'Scarica originale', 'href' => static::originalUrl()],
                'delete',
            ]),
        ]));
    }

    /** L'indirizzo per scaricare l'originale, con `{id}` che la tabella riempie riga per riga. */
    protected static function originalUrl(): string
    {
        $url = __r('backend.media.original', ['id' => '0']);

        return $url === '' ? '' : (string) preg_replace('#/0/original/#', '/{id}/original/', $url, 1);
    }

    /**
     * Il valore del filtro "Utilizzo" all'apertura della lista: vuoto per
     * tutti, `library` per i soli caricati qui e non usati. Le Resource dei
     * tipi lo cambiano.
     */
    protected static function defaultUsageFilter(): string
    {
        return '';
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        $table = static::modelTable();

        $layout = TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters()
            ->select(MediaUsages::tablesSelect('usage_tables', $table))
            ->filterQuery(
                'Utilizzo',
                'usage',
                [
                    '' => ucfirst((string) static::getText('all')),
                    'library' => 'Solo libreria, non in uso',
                    'used' => ucfirst((string) static::getText('full')),
                    'unused' => ucfirst((string) static::getText('empty')),
                ],
                static fn (array $values): string => MediaUsages::filterCondition($values, $table),
                'radio',
                false,
                static::defaultUsageFilter()
            );

        // Una opzione per ogni sezione che usa media di questo tipo.
        $sections = MediaUsages::sections(static::mediaType());

        if ($sections !== []) {
            $allowed = array_keys($sections);
            $layout->filterQuery(
                'Sezione',
                'usage_section',
                $sections,
                static fn (array $values): string => MediaUsages::sectionCondition($values, $allowed, $table),
                'checkbox'
            );
        }

        return $layout;
    }

    /**
     * Un badge per ogni sezione che usa il media; cella vuota se nessuna.
     * `$tables` sono le tabelle separate da virgola della colonna calcolata.
     */
    public static function usageBadges(mixed $tables): string
    {
        if (!is_string($tables) || trim($tables) === '') {
            return '';
        }

        $labels = [];

        foreach (explode(',', $tables) as $table) {
            $table = trim($table);

            if ($table !== '') {
                $labels[MediaUsages::sectionLabel($table)] = true;
            }
        }

        $badges = array_map(
            static fn (string $label): string => Badge::make($label)->variant('secondary')->inline()->render('bootstrap'),
            array_keys($labels)
        );

        return '<div class="d-flex flex-wrap gap-1">'.implode('', $badges).'</div>';
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
        // L'origine la scrive solo chi crea il media da un campo (`DatabaseMediaStore`).
        unset($values['source']);

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
