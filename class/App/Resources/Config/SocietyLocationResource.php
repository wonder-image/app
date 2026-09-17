<?php

namespace Wonder\App\Resources\Config;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\OpeningHours;
use Wonder\App\Support\OpeningHoursInput;
use Wonder\App\Support\Repeater;
use Wonder\App\Support\SocietyLocationDefaults;
use Wonder\App\Support\SocietyLocationResolver;
use Wonder\App\Support\SocietyLocations;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

/**
 * "Sedi" della società, con orari e chiusure nella scheda. Una sede è
 * predefinita: le altre prendono da lei ciò che manca e il nome dell'attività.
 * Questa Resource dichiara la sezione "set-up" del backend (prima voce).
 */
final class SocietyLocationResource extends Resource
{
    public const PLACE_ID_FINDER_URL = 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder';

    public static string $model = SocietyLocation::class;
    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'app/config/locations';
    }

    public static function icon(): string
    {
        return 'bi-geo-alt';
    }

    public static function titleLabel(): string
    {
        return 'Sedi';
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
            'slug' => 'Slug (generato dal nome)',
            'name' => 'Nome dell\'attività',
            'is_default' => 'Predefinita',
            'visible' => 'Stato',
            'business_status' => 'Attività della sede',
            'opening_date' => 'Data di apertura',
            'google_place_id' => 'Google Place ID',
            'email' => 'Email',
            'pec' => 'Pec',
            'tel' => 'Telefono',
            'cel' => 'Cellulare',
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
            'hours' => 'Orari regolari e secondari',
            'special_hours' => 'Orari speciali e chiusure',
            'actions' => 'Azioni',
            ...SocietyLocation::address()->labels(),
            ...SocietyLocation::legalAddress()->labels(),
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('label')->text()->required(),
            FormField::key('slug')->text()->readonly()->placeholder('Generato dal nome della sede'),
            FormField::key('name')->text()->visibleWhen('is_default', 'true'),
            FormField::key('is_default')->select(['true' => 'Sì', 'false' => 'No'])->value('false')->required(),
            FormField::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosta'])->value('true')->required(),
            FormField::key('business_status')->select([
                'operational' => 'Operativa',
                'closed_temporarily' => 'Chiusa temporaneamente',
                'closed_permanently' => 'Chiusa definitivamente',
                'future_opening' => 'Apertura futura',
            ])->value('operational')->required(),
            FormField::key('opening_date')->dateInput(),
            ...array_values(AddressExtension::simple(linkKey: 'gmaps', countryDefault: 'IT')->formSchema()),
            FormField::key('google_place_id')->text(),
            FormField::key('email')->email(),
            FormField::key('pec')->text(),
            FormField::key('tel')->text(),
            FormField::key('cel')->text(),
            FormField::key('legal_name')->text(),
            FormField::key('share_capital')->price(),
            FormField::key('sdi')->text(),
            FormField::key('rea')->text(),
            FormField::key('pi')->text(),
            FormField::key('cf')->text(),
            ...array_values(AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps', countryDefault: 'IT')->formSchema()),
            FormField::key('site')->url(),
            FormField::key('instagram')->url(),
            FormField::key('facebook')->url(),
            FormField::key('tiktok')->url(),
            FormField::key('linkedin')->url(),
            FormField::key('whatsapp')->url(),
            FormField::key('youtube')->url(),
            FormField::key('hours')
                ->repeater([
                    RepeaterColumn::key('id')->hidden(),
                    RepeaterColumn::key('hours_type')->select(self::hoursTypes())->value(OpeningHours::REGULAR)->label('Tipo')->columnSpan(3),
                    RepeaterColumn::key('open_day')->select(self::days())->label('Apre il')->columnSpan(2),
                    RepeaterColumn::key('open_time')->timeInput(900)->label('Alle')->columnSpan(2),
                    RepeaterColumn::key('close_day')->select(['' => 'Stesso giorno'] + self::days())->label('Chiude il')->columnSpan(2),
                    RepeaterColumn::key('close_time')->timeInput(900)->label('Alle')->columnSpan(2),
                ])
                ->relation(
                    RepeaterRelation::make(SocietyLocationHour::$table, 'society_location_id')
                        ->model(SocietyLocationHour::class)
                        ->positionKey('position')
                )
                ->nested()
                ->repeaterSortable()
                ->repeaterAddLabel('Aggiungi fascia oraria')
                ->repeaterDeleteTitle('Elimina fascia oraria')
                ->repeaterDeleteText('Confermi l\'eliminazione di questa fascia oraria?')
                ->repeaterDeleteCancelLabel('Annulla')
                ->repeaterDeleteConfirmLabel('Elimina')
                ->repeaterDeleteConfirmClass('btn btn-danger')
                ->label('Orari regolari e secondari'),
            FormField::key('special_hours')
                ->repeater([
                    RepeaterColumn::key('id')->hidden(),
                    RepeaterColumn::key('start_date')->dateInput()->label('Dal')->columnSpan(2),
                    RepeaterColumn::key('end_date')->dateInput()->label('Al')->columnSpan(2),
                    RepeaterColumn::key('closed')->select(['true' => 'Chiuso', 'false' => 'Aperto'])->value('true')->label('Stato')->columnSpan(2),
                    RepeaterColumn::key('open_time')->timeInput(900)->label('Apre')->columnSpan(2),
                    RepeaterColumn::key('close_time')->timeInput(900)->label('Chiude')->columnSpan(2),
                    RepeaterColumn::key('note')->text()->label('Nota')->columnSpan(11),
                ])
                ->relation(
                    RepeaterRelation::make(SocietyLocationSpecialHour::$table, 'society_location_id')
                        ->model(SocietyLocationSpecialHour::class)
                )
                ->nested()
                ->repeaterAddLabel('Aggiungi chiusura o apertura straordinaria')
                ->repeaterDeleteTitle('Elimina orario speciale')
                ->repeaterDeleteText('Confermi l\'eliminazione di questo orario speciale?')
                ->repeaterDeleteCancelLabel('Annulla')
                ->repeaterDeleteConfirmLabel('Elimina')
                ->repeaterDeleteConfirmClass('btn btn-danger')
                ->label('Orari speciali e chiusure'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Sede')
                        ->tooltip('I campi vuoti prendono i dati dalla sede predefinita: contatti e link uno per uno; indirizzo, sede legale, dati legali e orari solo se il riquadro è tutto vuoto. Il nome dell\'attività si compila nella sede predefinita e vale per tutte le sedi. Lo slug nasce dal nome della sede alla creazione e non cambia.')
                        ->columnSpan(12),
                    static::getInput('label')->columnSpan(5),
                    static::getInput('slug')->columnSpan(4),
                    static::getInput('visible')->columnSpan(3),
                    static::getInput('is_default')->columnSpan(3),
                    static::getInput('business_status')->columnSpan(3),
                    static::getInput('opening_date')->columnSpan(3),
                    static::getInput('name')->columnSpan(3),
                ])->columns(12)->columnSpan(2),

                (new Card)->components([
                    SectionTitle::make('Contatti')->columnSpan(12),
                    static::getInput('email')->columnSpan(6),
                    static::getInput('pec')->columnSpan(6),
                    static::getInput('tel')->columnSpan(6),
                    static::getInput('cel')->columnSpan(6),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Dati legali')->columnSpan(12),
                    static::getInput('legal_name')->columnSpan(12),
                    static::getInput('pi')->columnSpan(6),
                    static::getInput('cf')->columnSpan(6),
                    static::getInput('sdi')->columnSpan(4),
                    static::getInput('rea')->columnSpan(4),
                    static::getInput('share_capital')->columnSpan(4),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Indirizzo')
                        ->tooltip('Se il link a Google Maps è vuoto si costruisce dal Place ID.')
                        ->columnSpan(12),
                    static::getInput('country')->columnSpan(6),
                    static::getInput('province')->columnSpan(6),
                    static::getInput('city')->columnSpan(8),
                    static::getInput('cap')->columnSpan(4),
                    static::getInput('street')->columnSpan(10),
                    static::getInput('number')->columnSpan(2),
                    static::getInput('more')->columnSpan(12),
                    static::getInput('gmaps')->columnSpan(12),
                    static::getInput('google_place_id')->columnSpan(12),
                    HelpText::make('<a href="'.self::PLACE_ID_FINDER_URL.'" target="_blank" rel="noopener noreferrer">Trova il Place ID</a>')->columnSpan(12),
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
                    SectionTitle::make('Orari e chiusure')
                        ->tooltip('Più fasce nello stesso giorno sono più righe (es. 9–13 e 15–19). Per chiudere dopo la mezzanotte scegli il giorno dopo; per chiudere a mezzanotte usa 00:00. Senza orario di chiusura la sede è sempre aperta. Le chiusure possono durare più giorni; un\'apertura straordinaria vale un giorno e vale per gli orari regolari. Una sede senza orari propri usa orari e chiusure della predefinita.')
                        ->columnSpan(12),
                    static::getInput('hours')->columnSpan(12),
                    static::getInput('special_hours')->columnSpan(12),
                ])->columns(12)->columnSpan(2),

            ])->columns(2)->columnSpan(9),

            (new Container)->components([

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
                'list' => 'Sedi',
                'create' => 'Nuova sede',
                'edit' => 'Modifica sede',
            ]);
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
            ->title('Sedi')
            ->order(10)
            ->authority(['admin']);
    }

    /**
     * Slug generato dal nome della sede solo alla creazione; nome dell'attività
     * solo nella predefinita; orari validati prima di salvare la sede.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        global $ALERT;

        if ($action === 'store') {
            $values['slug'] = (string) ($values['label'] ?? '');
            $values['position'] = self::nextPosition();
        } else {
            unset($values['slug']);
        }

        $values['is_default'] = SocietyLocationDefaults::flagOnSave(
            $values['is_default'] ?? 'false',
            self::otherDefaultExists((int) ($oldValues['id'] ?? 0))
        );

        if ($values['is_default'] !== 'true') {
            $values['name'] = '';
        }

        $errors = array_merge(
            OpeningHoursInput::hours(Repeater::rowsFromRequest('hours', $_POST, $_FILES))['errors'],
            OpeningHoursInput::specialHours(Repeater::rowsFromRequest('special_hours', $_POST, $_FILES))['errors']
        );

        if ($errors !== []) {
            $ALERT = implode(' ', $errors);
        }

        return $values;
    }

    public static function prepareRepeaterRows(
        string $inputName,
        array $rows,
        string $action = 'store',
        string $context = 'backend'
    ): array {
        return match ($inputName) {
            'hours' => OpeningHoursInput::hours($rows)['rows'],
            'special_hours' => OpeningHoursInput::specialHours($rows)['rows'],
            default => $rows,
        };
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        // Dopo un errore il form riceve i valori preparati, senza lo slug immutabile.
        if (trim((string) ($values['slug'] ?? '')) === '' && (int) ($values['id'] ?? 0) > 0) {
            $row = sqlSelect(SocietyLocation::$table, ['id' => (int) $values['id']], 1)->row;
            $values['slug'] = is_array($row) ? (string) ($row['slug'] ?? '') : '';
        }

        foreach (['hours', 'special_hours'] as $inputName) {
            if (is_array($values[$inputName] ?? null)) {
                $values[$inputName] = OpeningHoursInput::forForm($values[$inputName]);
            }
        }

        if (is_array($values['special_hours'] ?? null)) {
            usort(
                $values['special_hours'],
                static fn ($a, $b): int => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''))
            );
        }

        return $values;
    }

    public static function formPlaceholders(array $values, string $mode): array
    {
        if (($values['is_default'] ?? '') === 'true') {
            return [];
        }

        $default = SocietyLocation::find(['is_default' => 'true'], 1);

        if (!is_array($default) || $default === []) {
            return [];
        }

        // Paese e provincia sono select: il suggerimento non si vedrebbe.
        return array_diff_key(
            SocietyLocationResolver::inheritedValues($values, $default),
            array_flip(['country', 'province', 'legal_country', 'legal_province'])
        );
    }

    public static function afterStore(object $result, array $values = []): void
    {
        self::keepSingleDefault((int) ($result->insert_id ?? 0), $values);
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::keepSingleDefault((int) $id, $values);
    }

    public static function afterDelete(int|string $id, object $result, array $values = []): void
    {
        SocietyLocations::reset();
    }

    public static function assertDeletable(int|string $id): void
    {
        $row = SocietyLocation::findById($id);

        if (is_array($row) && !SocietyLocationDefaults::canDelete($row)) {
            throw new RuntimeException('La sede predefinita non si può eliminare: imposta prima un\'altra sede come predefinita.');
        }
    }

    /**
     * Toglie il flag alle altre sedi e passa il nome dell'attività alla nuova
     * predefinita, se non ne ha uno. Le righe si leggono senza normalizzazione,
     * così il nome si copia con la stessa codifica del database.
     */
    private static function keepSingleDefault(int $id, array $values): void
    {
        if ($id > 0 && ($values['is_default'] ?? '') === 'true') {
            $businessName = trim((string) ($values['name'] ?? ''));

            foreach (self::defaultRows() as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    continue;
                }

                if ($businessName === '' && trim((string) ($row['name'] ?? '')) !== '') {
                    $businessName = (string) $row['name'];
                    sqlModify(SocietyLocation::$table, ['name' => $businessName], 'id', $id);
                }

                sqlModify(SocietyLocation::$table, ['is_default' => 'false', 'name' => ''], 'id', (int) $row['id']);
            }
        }

        SocietyLocations::reset();
    }

    private static function otherDefaultExists(int $id): bool
    {
        foreach (self::defaultRows() as $row) {
            if ((int) ($row['id'] ?? 0) !== $id) {
                return true;
            }
        }

        return false;
    }

    /** Posizione in fondo all'elenco per una nuova sede. */
    private static function nextPosition(): int
    {
        $last = sqlSelect(SocietyLocation::$table, ['deleted' => 'false'], 1, 'position', 'DESC')->row;

        return is_array($last) && $last !== [] ? (int) ($last['position'] ?? 0) + 1 : 1;
    }

    /** @return list<array<string, mixed>> */
    private static function defaultRows(): array
    {
        $rows = sqlSelect(SocietyLocation::$table, ['is_default' => 'true', 'deleted' => 'false'])->row;

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array<string, string> */
    private static function days(): array
    {
        $days = [];

        foreach (OpeningHours::DAYS as $day) {
            $days[$day] = translateDate($day, 'day');
        }

        return $days;
    }

    /** @return array<string, string> */
    private static function hoursTypes(): array
    {
        return [
            OpeningHours::REGULAR => 'Orari regolari',
            'delivery' => 'Consegna a domicilio',
            'takeout' => 'Asporto',
            'pickup' => 'Ritiro',
            'drive_through' => 'Drive-through',
            'kitchen' => 'Cucina',
            'breakfast' => 'Colazione',
            'brunch' => 'Brunch',
            'lunch' => 'Pranzo',
            'dinner' => 'Cena',
            'happy_hour' => 'Happy hour',
            'access' => 'Accesso',
            'senior_hours' => 'Fascia anziani',
            'online_service_hours' => 'Servizio online',
        ];
    }
}
