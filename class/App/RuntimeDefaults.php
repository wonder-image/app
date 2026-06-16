<?php

namespace Wonder\App;

class RuntimeDefaults
{
    public static function defaultFonts(): array
    {
        return [
            [
                'name' => 'Roboto',
                'link' => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap',
                'font-family' => '"Roboto", sans-serif',
            ],
            [
                'name' => 'Montserrat',
                'link' => 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap',
                'font-family' => '"Montserrat", sans-serif',
            ],
        ];
    }

    public static function defaultColors(): array
    {
        return [
            [ 'var' => 'primary', 'name' => 'Primario', 'color' => '#000000', 'contrast' => '#ffffff' ],
            [ 'var' => 'secondary', 'name' => 'Secondario', 'color' => '#ffffff', 'contrast' => '#000000' ],
            [ 'var' => 'success', 'name' => 'Successo', 'color' => '#28a745', 'contrast' => '#ffffff' ],
            [ 'var' => 'info', 'name' => 'Informazione', 'color' => '#17a2b8', 'contrast' => '#ffffff' ],
            [ 'var' => 'danger', 'name' => 'Pericolo', 'color' => '#dc3545', 'contrast' => '#ffffff' ],
            [ 'var' => 'dark', 'name' => 'Scuro', 'color' => '#343a40', 'contrast' => 'var(--light-color)' ],
            [ 'var' => 'light', 'name' => 'Chiaro', 'color' => '#f8f9fa', 'contrast' => 'var(--dark-color)' ],
            [ 'var' => 'gray', 'name' => 'Grigio', 'color' => '#eeeeee', 'contrast' => '#000000' ],
            [ 'var' => 'black', 'name' => 'Nero', 'color' => '#000000', 'contrast' => '#ffffff' ],
            [ 'var' => 'white', 'name' => 'Bianco', 'color' => '#ffffff', 'contrast' => '#000000' ],
        ];
    }

    public static function defaultUserColors(): array
    {
        return [
            'blue' => [ 'name' => 'Blu', 'color' => '#0000ff', 'contrast' => '#ffffff', 'active' => true ],
            'green' => [ 'name' => 'Verde', 'color' => '#008000', 'contrast' => '#ffffff', 'active' => true ],
            'purple' => [ 'name' => 'Viola', 'color' => '#800080', 'contrast' => '#ffffff', 'active' => true ],
            'red' => [ 'name' => 'Rosso', 'color' => '#FF0000', 'contrast' => '#000000', 'active' => true ],
            'yellow' => [ 'name' => 'Giallo', 'color' => '#FFFF00', 'contrast' => '#000000', 'active' => true ],
            'pink' => [ 'name' => 'Rosa', 'color' => '#FFC0CB', 'contrast' => '#000000', 'active' => true ],
            'orange' => [ 'name' => 'Arancione', 'color' => '#FFA500', 'contrast' => '#000000', 'active' => true ],
            'turquoise' => [ 'name' => 'Turchese', 'color' => '#40E0D0', 'contrast' => '#000000', 'active' => true ],
            'gold' => [ 'name' => 'Oro', 'color' => '#FFD700', 'contrast' => '#000000', 'active' => true ],
            'silver' => [ 'name' => 'Argento', 'color' => '#C0C0C0', 'contrast' => '#000000', 'active' => true ],
        ];
    }

    public static function defaultAppIconSizes(): array
    {
        return [ '196', '180', '152', '144', '120', '114', '76', '72', '57', '32', '16' ];
    }

    public static function defaultImage(?object $path = null): string
    {
        $assets = is_object($path) ? (string) ($path->assets ?? '') : '';

        return $assets !== '' ? $assets.'/images/Default.png' : '';
    }

    public static function backendLogoBlack(?object $path = null): string
    {
        $appAssets = is_object($path) ? (string) ($path->appAssets ?? '') : '';

        return $appAssets !== '' ? $appAssets.'/logos/Wonder-Image.png' : '';
    }

    public static function backendLogoWhite(?object $path = null): string
    {
        $appAssets = is_object($path) ? (string) ($path->appAssets ?? '') : '';

        return $appAssets !== '' ? $appAssets.'/logos/Wonder-Image-White.png' : '';
    }

    public static function backendFavicon(): string
    {
        return 'https://www.wonderimage.it/favicon.ico';
    }

    public static function mergeStyleDefaults(?object $current, ?object $path = null): object
    {
        $defaults = (object) [
            'color' => self::defaultColors(),
            'colorUser' => self::defaultUserColors(),
            'font' => self::defaultFonts(),
            'BeLogoBlack' => self::backendLogoBlack($path),
            'BeLogoWhite' => self::backendLogoWhite($path),
            'BeFavicon' => self::backendFavicon(),
            'image' => self::defaultImage($path),
            'appIcon' => self::defaultAppIconSizes(),
        ];

        if (!is_object($current)) {
            return $defaults;
        }

        foreach (get_object_vars($current) as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value) && empty($value) && isset($defaults->$key)) {
                continue;
            }

            if ($value === '' && isset($defaults->$key)) {
                continue;
            }

            $defaults->$key = $value;
        }

        return $defaults;
    }

}
