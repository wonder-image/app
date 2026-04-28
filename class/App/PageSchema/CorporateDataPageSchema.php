<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormInput;
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
            'name' => FormInput::key('name')->text(),
            'email' => FormInput::key('email')->email(),
            'tel' => FormInput::key('tel')->text(),
            'cel' => FormInput::key('cel')->text(),
        ]);
    }

    public static function legalFormSchema(): array
    {
        return static::applyLabelSchema([
            'legal_name' => FormInput::key('legal_name')->text(),
            'share_capital' => FormInput::key('share_capital')->price(),
            'pec' => FormInput::key('pec')->text(),
            'sdi' => FormInput::key('sdi')->text(),
            'rea' => FormInput::key('rea')->text(),
            'pi' => FormInput::key('pi')->text(),
            'cf' => FormInput::key('cf')->text(),
        ]);
    }

    public static function legalAddressFormSchema(string $country = 'IT'): array
    {
        return static::applyLabelSchema([
            'legal_country' => FormInput::key('legal_country')->country('legal_province'),
            'legal_province' => FormInput::key('legal_province')->states($country),
            'legal_city' => FormInput::key('legal_city')->text(),
            'legal_cap' => FormInput::key('legal_cap')->text(),
            'legal_street' => FormInput::key('legal_street')->text(),
            'legal_number' => FormInput::key('legal_number')->text(),
            'legal_more' => FormInput::key('legal_more')->text(),
            'legal_gmaps' => FormInput::key('legal_gmaps')->url(),
        ]);
    }

    public static function addressFormSchema(string $country = 'IT'): array
    {
        return static::applyLabelSchema([
            'country' => FormInput::key('country')->country('province'),
            'province' => FormInput::key('province')->states($country),
            'city' => FormInput::key('city')->text(),
            'cap' => FormInput::key('cap')->text(),
            'street' => FormInput::key('street')->text(),
            'number' => FormInput::key('number')->text(),
            'more' => FormInput::key('more')->text(),
            'gmaps' => FormInput::key('gmaps')->url(),
        ]);
    }

    public static function socialFormSchema(): array
    {
        return static::applyLabelSchema([
            'site' => FormInput::key('site')->url(),
            'instagram' => FormInput::key('instagram')->url(),
            'facebook' => FormInput::key('facebook')->url(),
            'tiktok' => FormInput::key('tiktok')->url(),
            'linkedin' => FormInput::key('linkedin')->url(),
            'whatsapp' => FormInput::key('whatsapp')->url(),
            'youtube' => FormInput::key('youtube')->url(),
        ]);
    }

    public static function timetableFormSchema(): array
    {
        return static::applyLabelSchema([
            'day' => FormInput::key('day')
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
            'from_time' => FormInput::key('from_time')->text()->columnSpan(3),
            'to_time' => FormInput::key('to_time')->text()->columnSpan(3),
        ]);
    }

    public static function timetableRepeaterField(): object
    {
        return FormInput::key('timetables')
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
                RepeaterColumn::key('from_time')->text()->label('Da (08:00)')->columnSpan(3),
                RepeaterColumn::key('to_time')->text()->label('A (17:30)')->columnSpan(4),
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
