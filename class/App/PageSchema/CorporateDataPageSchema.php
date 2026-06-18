<?php

namespace Wonder\App\PageSchema;

use Wonder\App\Schema\Extensions\AddressExtension;
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
            ...AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->labels(),
            ...AddressExtension::simple(linkKey: 'gmaps')->labels(),
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
        return static::applyLabelSchema(
            AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->formSchema($country)
        );
    }

    public static function addressFormSchema(string $country = 'IT'): array
    {
        return static::applyLabelSchema(
            AddressExtension::simple(linkKey: 'gmaps')->formSchema($country)
        );
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
