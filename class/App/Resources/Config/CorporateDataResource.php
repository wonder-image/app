<?php

namespace Wonder\App\Resources\Config;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\SocietyLocationDefaults;
use Wonder\App\Support\SocietyLocationResolver;
use Wonder\App\Support\SocietyLocations;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

/**
 * "Dati aziendali": sedi della società. Una sede è predefinita e le altre
 * prendono da lei ciò che manca. Questa Resource dichiara la sezione
 * "set-up" del backend (prima voce, order 10).
 */
final class CorporateDataResource extends Resource
{
    public const PLACE_ID_FINDER_URL = 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder';

    public static string $model = SocietyLocation::class;
    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'app/config/corporate-data';
    }

    public static function icon(): string
    {
        return 'bi-building';
    }

    public static function titleLabel(): string
    {
        return 'Dati aziendali';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'sede',
            'plural_label' => 'sedi',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'visibile',
            'empty' => 'nascosta',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'label' => 'Nome della sede',
            'slug' => 'Slug',
            'is_default' => 'Predefinita',
            'visible' => 'Stato',
            'business_status' => 'Attività',
            'opening_date' => 'Data di apertura',
            'google_place_id' => 'Google Place ID',
            'email' => 'Email',
            'pec' => 'Pec',
            'tel' => 'Telefono',
            'cel' => 'Cellulare',
            'name' => 'Nome',
            'legal_name' => 'Nome legale',
            'share_capital' => 'C.Sociale',
            'sdi' => 'SDI',
            'rea' => 'R.E.A.',
            'pi' => 'P.Iva',
            'cf' => 'C.Fiscale',
            'site' => 'Sito',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'linkedin' => 'Linkedin',
            'whatsapp' => 'WhatsApp',
            'youtube' => 'Youtube',
            'actions' => 'Azioni',
            ...SocietyLocation::address()->labels(),
            ...SocietyLocation::legalAddress()->labels(),
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('label')->text()->required(),
            FormField::key('slug')->text(),
            FormField::key('is_default')->select(['true' => 'Sì', 'false' => 'No'])->value('false')->required(),
            FormField::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosta'])->value('true')->required(),
            FormField::key('business_status')->select([
                'operational' => 'Operativa',
                'closed_temporarily' => 'Chiusa temporaneamente',
                'closed_permanently' => 'Chiusa definitivamente',
                'future_opening' => 'Apertura futura',
            ])->value('operational')->required(),
            FormField::key('opening_date')->dateInput(),
            ...array_values(SocietyLocation::address()->formSchema()),
            FormField::key('google_place_id')->text(),
            FormField::key('email')->email(),
            FormField::key('pec')->text(),
            FormField::key('tel')->text(),
            FormField::key('cel')->text(),
            FormField::key('name')->text(),
            FormField::key('legal_name')->text(),
            FormField::key('share_capital')->price(),
            FormField::key('sdi')->text(),
            FormField::key('rea')->text(),
            FormField::key('pi')->text(),
            FormField::key('cf')->text(),
            ...array_values(SocietyLocation::legalAddress()->formSchema()),
            FormField::key('site')->url(),
            FormField::key('instagram')->url(),
            FormField::key('facebook')->url(),
            FormField::key('tiktok')->url(),
            FormField::key('linkedin')->url(),
            FormField::key('whatsapp')->url(),
            FormField::key('youtube')->url(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Sede')->columnSpan(12),
                    static::getInput('label')->columnSpan(8),
                    static::getInput('slug')->columnSpan(4),
                    HelpText::make('I campi vuoti prendono i dati dalla sede predefinita: contatti e link uno per uno; dati aziendali, indirizzo e sede legale solo se il riquadro è tutto vuoto.')->columnSpan(12),
                ])->columns(12)->columnSpan(2),

                (new Card)->components([
                    SectionTitle::make('Indirizzo')->columnSpan(12),
                    static::getInput('country')->columnSpan(6),
                    static::getInput('province')->columnSpan(6),
                    static::getInput('city')->columnSpan(8),
                    static::getInput('cap')->columnSpan(4),
                    static::getInput('street')->columnSpan(10),
                    static::getInput('number')->columnSpan(2),
                    static::getInput('more')->columnSpan(12),
                    static::getInput('gmaps')->columnSpan(12),
                    static::getInput('google_place_id')->columnSpan(12),
                    HelpText::make('Trova il Place ID con il <a href="'.self::PLACE_ID_FINDER_URL.'" target="_blank" rel="noopener noreferrer">Place ID Finder di Google</a>. Se il link a Google Maps è vuoto si costruisce dal Place ID.')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Sede legale')->columnSpan(12),
                    static::getInput('legal_country')->columnSpan(6),
                    static::getInput('legal_province')->columnSpan(6),
                    static::getInput('legal_city')->columnSpan(8),
                    static::getInput('legal_cap')->columnSpan(4),
                    static::getInput('legal_street')->columnSpan(10),
                    static::getInput('legal_number')->columnSpan(2),
                    static::getInput('legal_more')->columnSpan(12),
                    static::getInput('legal_gmaps')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Contatti')->columnSpan(12),
                    static::getInput('email')->columnSpan(6),
                    static::getInput('pec')->columnSpan(6),
                    static::getInput('tel')->columnSpan(6),
                    static::getInput('cel')->columnSpan(6),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Dati aziendali e legali')->columnSpan(12),
                    static::getInput('name')->columnSpan(6),
                    static::getInput('legal_name')->columnSpan(6),
                    static::getInput('pi')->columnSpan(6),
                    static::getInput('cf')->columnSpan(6),
                    static::getInput('sdi')->columnSpan(4),
                    static::getInput('rea')->columnSpan(4),
                    static::getInput('share_capital')->columnSpan(4),
                ])->columns(12)->columnSpan(1),

            ])->columns(2)->columnSpan(9),

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Stato')->columnSpan(12),
                    static::getInput('is_default')->columnSpan(12),
                    static::getInput('visible')->columnSpan(12),
                    static::getInput('business_status')->columnSpan(12),
                    static::getInput('opening_date')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Link')->columnSpan(12),
                    static::getInput('site')->columnSpan(12),
                    static::getInput('instagram')->columnSpan(12),
                    static::getInput('facebook')->columnSpan(12),
                    static::getInput('tiktok')->columnSpan(12),
                    static::getInput('linkedin')->columnSpan(12),
                    static::getInput('whatsapp')->columnSpan(12),
                    static::getInput('youtube')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

            ])->columns(1)->columnSpan(3),

        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('label')->text()->link('edit'),
            TableColumn::key('city')->text(),
            TableColumn::key('is_default')
                ->booleanBadge()
                ->badgeOn('Predefinita', 'bi bi-star-fill', 'primary')
                ->badgeOff('Secondaria')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge()->size('little'),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Sedi')
            ->results()
            ->buttonAdd('Aggiungi sede')
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->titles([
                'list' => 'Dati aziendali',
                'create' => 'Nuova sede',
                'edit' => 'Modifica sede',
            ])
            ->actions('edit', static fn (array $item): array => [[
                'label' => 'Orari e chiusure',
                'icon' => 'bi bi-clock',
                'class' => 'btn-outline-secondary',
                'href' => __r('backend.resource.'.OpeningHoursResource::slug().'.edit', ['id' => (int) ($item['id'] ?? 0)]),
            ]]);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('set-up', 'Set Up', 'bi-gear', 1020, ['admin'])
            ->title('Dati aziendali')
            ->order(10)
            ->authority(['admin']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (trim((string) ($values['slug'] ?? '')) === '' && trim((string) ($values['label'] ?? '')) !== '') {
            $values['slug'] = $values['label'];
        }

        $values['is_default'] = SocietyLocationDefaults::flagOnSave(
            $values['is_default'] ?? 'false',
            self::otherDefaultExists((int) ($oldValues['id'] ?? 0))
        );

        return $values;
    }

    public static function formPlaceholders(array $values, string $mode): array
    {
        if (($values['is_default'] ?? '') === 'true') {
            return [];
        }

        $default = SocietyLocation::find(['is_default' => 'true'], 1);

        return is_array($default) && $default !== []
            ? SocietyLocationResolver::inheritedValues($values, $default)
            : [];
    }

    public static function afterStore(object $result, array $values = []): void
    {
        self::keepSingleDefault((int) ($result->insert_id ?? 0), $values);
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::keepSingleDefault((int) $id, $values);
    }

    public static function deleteRecord(int|string $id): object
    {
        $row = SocietyLocation::findById($id);

        if (is_array($row) && !SocietyLocationDefaults::canDelete($row)) {
            throw new RuntimeException('La sede predefinita non si può eliminare: imposta prima un\'altra sede come predefinita.');
        }

        $result = parent::deleteRecord($id);
        SocietyLocations::reset();

        return $result;
    }

    private static function keepSingleDefault(int $id, array $values): void
    {
        if ($id > 0 && ($values['is_default'] ?? '') === 'true') {
            foreach ((array) SocietyLocation::find(['is_default' => 'true']) as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) !== $id) {
                    sqlModify(SocietyLocation::$table, ['is_default' => 'false'], 'id', (int) $row['id']);
                }
            }
        }

        SocietyLocations::reset();
    }

    private static function otherDefaultExists(int $id): bool
    {
        foreach ((array) SocietyLocation::find(['is_default' => 'true']) as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) !== $id) {
                return true;
            }
        }

        return false;
    }
}
