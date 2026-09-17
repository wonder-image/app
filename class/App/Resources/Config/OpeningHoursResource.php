<?php

namespace Wonder\App\Resources\Config;

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
use Wonder\App\Support\OpeningHours;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;
use Wonder\Http\Route;

/**
 * "Orari e chiusure" delle sedi: orari regolari e secondari, orari speciali
 * e chiusure. Modificabile da `admin` e `administrator` anche in produzione;
 * la pagina di modifica è gestita da `OpeningHoursPageController`.
 */
final class OpeningHoursResource extends Resource
{
    public const AUTHORITIES = ['admin', 'administrator'];

    public static string $model = SocietyLocation::class;
    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'app/config/opening-hours';
    }

    public static function icon(): string
    {
        return 'bi bi-clock';
    }

    public static function titleLabel(): string
    {
        return 'Orari e chiusure';
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
            'label' => 'Sede',
            'city' => 'Città',
            'is_default' => 'Predefinita',
            'hours' => 'Orari regolari e secondari',
            'special_hours' => 'Orari speciali e chiusure',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
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
            (new Card)->components([
                SectionTitle::make('Orari regolari e secondari')->columnSpan(12),
                HelpText::make('Più fasce nello stesso giorno sono più righe (es. 9–13 e 15–19). Per chiudere dopo la mezzanotte scegli il giorno dopo in "Chiude il"; per chiudere a mezzanotte usa 00:00 dello stesso giorno. Una riga senza orario di chiusura indica "sempre aperto".')->columnSpan(12),
                static::getInput('hours')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
            (new Card)->components([
                SectionTitle::make('Orari speciali e chiusure')->columnSpan(12),
                HelpText::make('Le chiusure possono durare più giorni (es. ferie dal 10 al 25 agosto). Per un\'apertura straordinaria compila "Dal" e gli orari: se chiude dopo la mezzanotte vale fino al giorno dopo. Valgono per gli orari regolari.')->columnSpan(12),
                static::getInput('special_hours')->columnSpan(12),
            ])->columns(12)->columnSpan(12),
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
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Sedi')
            ->results()
            ->hideButtonAdd()
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['create', 'store', 'view', 'delete'])
            ->titles(['list' => 'Orari e chiusure', 'edit' => 'Orari e chiusure']);
    }

    public static function customBackendPages(): array
    {
        return ['edit', 'update'];
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/{id}/edit/', $rootApp.'/http/backend/config/opening-hours.php', [
            'resource' => $slug,
            'resource_action' => 'edit',
        ])->name('edit')
            ->permit(self::AUTHORITIES)
            ->where('id', '[0-9]+');

        Route::post('/{id}/edit/', $rootApp.'/http/backend/config/opening-hours.php', [
            'resource' => $slug,
            'resource_action' => 'update',
        ])->name('update')
            ->permit(self::AUTHORITIES)
            ->where('id', '[0-9]+');
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'edit', 'update'], self::AUTHORITIES);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Orari e chiusure')
            ->order(11)
            ->authority(self::AUTHORITIES);
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        if (is_array($values['special_hours'] ?? null)) {
            usort(
                $values['special_hours'],
                static fn ($a, $b): int => strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''))
            );
        }

        return $values;
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
