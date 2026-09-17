<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Resources\Config\OpeningHoursResource;
use Wonder\App\Support\OpeningHoursInput;
use Wonder\App\Support\Repeater;
use Wonder\App\Support\SocietyLocations;
use Wonder\Sql\Transaction;
use Wonder\View\View;

/**
 * Modifica di "Orari e chiusure" di una sede: valida i repeater e sincronizza
 * solo le tabelle degli orari, senza toccare la riga della sede.
 */
final class OpeningHoursPageController
{
    public static function handle(string $action, int $id): void
    {
        $location = $id > 0 ? SocietyLocation::findById($id) : null;

        if (!is_array($location) || $location === []) {
            throw new RuntimeException('Sede non trovata.');
        }

        $errors = [];
        $isUpdate = $action === 'update';

        if ($isUpdate) {
            $hours = OpeningHoursInput::hours(Repeater::rowsFromRequest('hours', $_POST, $_FILES));
            $special = OpeningHoursInput::specialHours(Repeater::rowsFromRequest('special_hours', $_POST, $_FILES));
            $errors = array_merge($hours['errors'], $special['errors']);

            if ($errors === []) {
                $relations = OpeningHoursResource::repeaterRelations();

                Transaction::run(static function () use ($relations, $id, $hours, $special): void {
                    Repeater::syncRelatedRows($relations['hours']['relation'], $id, $hours['rows']);
                    Repeater::syncRelatedRows($relations['special_hours']['relation'], $id, $special['rows']);
                });

                SocietyLocations::reset();

                header('Location: '.__r('backend.resource.'.OpeningHoursResource::slug().'.list'));
                exit();
            }
        }

        $values = OpeningHoursResource::hydrateRepeaterFormValues(
            $location,
            $id,
            $isUpdate ? $_POST : [],
            $isUpdate ? $_FILES : []
        );
        $presenter = new ResourcePagePresenter(OpeningHoursResource::class);
        $data = $presenter->form('edit', $values, $errors === [] ? [] : ['opening_hours' => implode(' ', $errors)], $id);

        $data['TITLE'] = 'Orari e chiusure · '.(string) ($location['label'] ?? '');
        $data['SUBTITLE'] = self::subtitle($id, $location);
        $data['FORM_ERROR_MESSAGE'] = implode(' ', $errors);

        View::make($presenter->viewPath('form'), $data)->render();
    }

    private static function subtitle(int $id, array $location): string
    {
        if (($location['is_default'] ?? '') === 'true') {
            return 'Sede predefinita: le sedi senza orari propri usano questi orari e queste chiusure.';
        }

        $ownHours = sqlSelect(SocietyLocationHour::$table, ['society_location_id' => $id, 'deleted' => 'false'], 1)->exists;

        return $ownHours
            ? ''
            : 'Questa sede usa orari e chiusure della sede predefinita finché non aggiungi orari propri.';
    }
}
