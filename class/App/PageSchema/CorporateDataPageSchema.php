<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;

final class CorporateDataPageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'name' => 'Nome',
            'email' => 'Email',
            'tel' => 'Telefono',
            'cel' => 'Cellulare',
            'legal_name' => 'Nome legale',
            'share_capital' => 'C.Sociale',
            'pec' => 'Pec',
            'sdi' => 'SDI',
            'rea' => 'R.E.A.',
            'pi' => 'P.Iva',
            'cf' => 'C.Fiscale',
            'legal_country' => 'Paese',
            'legal_province' => 'Provincia',
            'legal_city' => 'Città',
            'legal_cap' => 'Cap',
            'legal_street' => 'Via',
            'legal_number' => 'Civico',
            'legal_more' => 'Altro',
            'legal_gmaps' => 'Link gmaps',
            'country' => 'Paese',
            'province' => 'Provincia',
            'city' => 'Città',
            'cap' => 'Cap',
            'street' => 'Via',
            'number' => 'Civico',
            'more' => 'Altro',
            'gmaps' => 'Link gmaps',
            'site' => 'Sito',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'linkedin' => 'Linkedin',
            'whatsapp' => 'WhatsApp',
            'youtube' => 'Youtube',
            'day' => 'Giorno',
            'from_time' => 'Da (08:00)',
            'to_time' => 'A (17:30)',
        ];
    }

    public static function companyFormSchema(): array
    {
        return static::applyLabelSchema([
            'name' => FormField::key('name')->text(),
            'email' => FormField::key('email')->email(),
            'tel' => FormField::key('tel')->text(),
            'cel' => FormField::key('cel')->text(),
        ]);
    }

    public static function legalFormSchema(): array
    {
        return static::applyLabelSchema([
            'legal_name' => FormField::key('legal_name')->text(),
            'share_capital' => FormField::key('share_capital')->price(),
            'pec' => FormField::key('pec')->text(),
            'sdi' => FormField::key('sdi')->text(),
            'rea' => FormField::key('rea')->text(),
            'pi' => FormField::key('pi')->text(),
            'cf' => FormField::key('cf')->text(),
        ]);
    }

    public static function legalAddressFormSchema(string $country = 'IT'): array
    {
        return static::applyLabelSchema([
            'legal_country' => FormField::key('legal_country')->country('legal_province'),
            'legal_province' => FormField::key('legal_province')->states($country),
            'legal_city' => FormField::key('legal_city')->text(),
            'legal_cap' => FormField::key('legal_cap')->text(),
            'legal_street' => FormField::key('legal_street')->text(),
            'legal_number' => FormField::key('legal_number')->text(),
            'legal_more' => FormField::key('legal_more')->text(),
            'legal_gmaps' => FormField::key('legal_gmaps')->url(),
        ]);
    }

    public static function addressFormSchema(string $country = 'IT'): array
    {
        return static::applyLabelSchema([
            'country' => FormField::key('country')->country('province'),
            'province' => FormField::key('province')->states($country),
            'city' => FormField::key('city')->text(),
            'cap' => FormField::key('cap')->text(),
            'street' => FormField::key('street')->text(),
            'number' => FormField::key('number')->text(),
            'more' => FormField::key('more')->text(),
            'gmaps' => FormField::key('gmaps')->url(),
        ]);
    }

    public static function socialFormSchema(): array
    {
        return static::applyLabelSchema([
            'site' => FormField::key('site')->url(),
            'instagram' => FormField::key('instagram')->url(),
            'facebook' => FormField::key('facebook')->url(),
            'tiktok' => FormField::key('tiktok')->url(),
            'linkedin' => FormField::key('linkedin')->url(),
            'whatsapp' => FormField::key('whatsapp')->url(),
            'youtube' => FormField::key('youtube')->url(),
        ]);
    }

    public static function timetableFormSchema(): array
    {
        return static::applyLabelSchema([
            'day' => FormField::key('day')
                ->select([
                    'Mon' => translateDate('Mon', 'day'),
                    'Tue' => translateDate('Tue', 'day'),
                    'Wed' => translateDate('Wed', 'day'),
                    'Thu' => translateDate('Thu', 'day'),
                    'Fri' => translateDate('Fri', 'day'),
                    'Sat' => translateDate('Sat', 'day'),
                    'Sun' => translateDate('Sun', 'day'),
                ])
                ->columnSpan(3),
            'from_time' => FormField::key('from_time')->timeInput(900)->columnSpan(3),
            'to_time' => FormField::key('to_time')->timeInput(900)->columnSpan(3),
        ]);
    }

    public static function timetableRepeaterField(): object
    {
        return FormField::key('timetables')
            ->repeater([
                RepeaterColumn::key('id')->hidden(),
                RepeaterColumn::key('day')
                    ->select([
                        'Mon' => translateDate('Mon', 'day'),
                        'Tue' => translateDate('Tue', 'day'),
                        'Wed' => translateDate('Wed', 'day'),
                        'Thu' => translateDate('Thu', 'day'),
                        'Fri' => translateDate('Fri', 'day'),
                        'Sat' => translateDate('Sat', 'day'),
                        'Sun' => translateDate('Sun', 'day'),
                    ])
                    ->label('Giorno')
                    ->columnSpan(4),
                RepeaterColumn::key('from_time')->timeInput(900)->label('Da')->columnSpan(3),
                RepeaterColumn::key('to_time')->timeInput(900)->label('A')->columnSpan(4),
            ])
            ->nested()
            ->repeaterSortable()
            ->repeaterAddLabel('Aggiungi orario')
            ->repeaterDeleteTitle('Elimina orario')
            ->repeaterDeleteText('Confermi l\'eliminazione di questo orario?')
            ->repeaterDeleteCancelLabel('Annulla')
            ->repeaterDeleteConfirmLabel('Elimina orario')
            ->repeaterDeleteConfirmClass('btn btn-danger')
            ->label('Orari');
    }
}
