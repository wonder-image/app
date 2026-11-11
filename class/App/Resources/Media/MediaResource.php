<?php

namespace Wonder\App\Resources\Media;

use RuntimeException;
use Throwable;
use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\MediaUsages;
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

    abstract protected static function mediaType(): string;

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
        ]));
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components(array_values(array_filter([
                static::getInput('file')->columnSpan(4),
                static::getInput('name')->columnSpan(8),
                static::hasAlt() ? static::getInput('alt')->columnSpan(8) : null,
            ])))->columns(12)->columnSpan(9),
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

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $hasNewUpload = MediaFileManager::hasUploadedFiles($values['file'] ?? null);

        if (!empty($values['name'])) {
            if ($action === 'update' && !$hasNewUpload && !empty($oldValues['slug'])) {
                $values['slug'] = (string) $oldValues['slug'];
            } else {
                $values['slug'] = (string) $values['name'];
            }
        } elseif ($action === 'update' && !empty($oldValues['slug'])) {
            $values['slug'] = (string) $oldValues['slug'];
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
            throw new RuntimeException(static::inUseMessage($where));
        }
    }

    /**
     * Il file di un media in uso non si toglie dall'elenco: resterebbe un
     * media senza file dovunque è usato. Si sostituisce dalla scheda.
     */
    public static function assertFileDeletable(int|string $id, string $column): void
    {
        static::assertDeletable($id);
    }

    /** Avviso nella scheda: dove è usato il media, se lo è. */
    public static function formNotices(array $values, string $mode): array
    {
        $id = $values['id'] ?? null;

        if ($mode !== 'edit' || !(is_int($id) || is_string($id))) {
            return [];
        }

        try {
            $where = MediaUsages::message($id);
        } catch (Throwable) {
            // Registro non ancora creato (prima di `forge update`): niente avviso.
            return [];
        }

        return $where === '' ? [] : [['level' => 'info', 'message' => $where]];
    }

    public static function afterDelete(int|string $id, object $result, array $values = []): void
    {
        if (empty($result->success) || empty($values['file'])) {
            return;
        }

        MediaFileManager::deleteFiles(
            ROOT.'/assets/upload/'.trim(static::legacyFolder(), '/'),
            static::fileCleanupFormat(),
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
