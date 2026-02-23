<?php

namespace Wonder\Plugin\Custom\Translator;

use Wonder\Support\Prettify\Date;

/**
 * DEPRECATO: usa Wonder\Support\Prettify\Date::day()/month().
 */
class TranslatorDate
{
    public static function Day($date): string
    {
        return Date::day($date);
    }

    public static function Month($date): string
    {
        return Date::month($date);
    }
}
