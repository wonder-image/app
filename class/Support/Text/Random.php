<?php

namespace Wonder\Support\Text;

class Random
{
    public static string $letter = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    public static string $number = "0123456789";
    public static string $characters = "";

    public function __construct(string $type = 'all')
    {
        self::init($type);
    }

    public static function init(string $type = 'all'): void
    {
        if ($type == 'all') {
            self::$characters = self::$letter . self::$number;
        } elseif ($type == 'letters' || $type == 'letter') {
            self::$characters = self::$letter;
        } elseif ($type == 'numbers' || $type == 'number') {
            self::$characters = self::$number;
        }
    }

    public static function generate(int $length = 10, ?string $prefix = null): string
    {
        if (empty(self::$characters)) {
            self::init();
        }

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::$characters[rand(0, strlen(self::$characters) - 1)];
        }

        if (!empty($prefix)) {
            $code = $prefix . $code;
        }

        return $code;
    }
}
