<?php

namespace Wonder\Support\Prettify;

class Date
{
    public static function day($date): string
    {
        $day = strtolower(date("l", strtotime($date)));

        return __t("date.week.$day");
    }

    public static function month($date): string
    {
        $month = strtolower(date("F", strtotime($date)));

        return __t("date.month.$month");
    }

    public static function prettify($date, $hours = false): string
    {
        if (empty($date)) {
            return "";
        }

        $return = date("d", strtotime($date)) . ' ' . self::month($date) . ' ' . date("Y", strtotime($date));
        $return .= $hours ? ' ' . __t('date.at_hours', ['hours' => date("H:i", strtotime($date))]) : '';

        return $return;
    }
}
