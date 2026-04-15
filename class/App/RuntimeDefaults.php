<?php

namespace Wonder\App;

class RuntimeDefaults
{
    public static function adminUsername(): string
    {
        return 'admin';
    }

    public static function adminName(): string
    {
        return 'Admin';
    }

    public static function adminSurname(): string
    {
        return 'User';
    }

    public static function adminEmail(): string
    {
        return 'info@wonderimage.it';
    }

    public static function systemEmail(): string
    {
        return 'system@wonderimage.it';
    }

    public static function githubEmail(): string
    {
        return 'github@wonderimage.it';
    }

    public static function defaultFonts(): array
    {
        return [
            [
                'name' => 'Roboto',
                'link' => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap',
                'font-family' => "'Roboto', sans-serif",
            ],
            [
                'name' => 'Montserrat',
                'link' => 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap',
                'font-family' => "'Montserrat', sans-serif",
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

    public static function cssDefaultRow(): array
    {
        return [
            'font_id' => 1,
            'font_weight' => 400,
            'font_size' => 16,
            'line_height' => 16,
            'title_big_font_id' => 1,
            'title_big_font_weight' => 500,
            'title_big_font_size' => 40,
            'title_big_line_height' => 40,
            'title_font_id' => 1,
            'title_font_weight' => 500,
            'title_font_size' => 32,
            'title_line_height' => 32,
            'subtitle_font_id' => 1,
            'subtitle_font_weight' => 400,
            'subtitle_font_size' => 24,
            'subtitle_line_height' => 24,
            'text_font_id' => 1,
            'text_font_weight' => 300,
            'text_font_size' => 16,
            'text_line_height' => 20,
            'text_small_font_id' => 1,
            'text_small_font_weight' => 300,
            'text_small_font_size' => 12,
            'text_small_line_height' => 12,
            'button_font_size' => 14,
            'button_line_height' => 16,
            'button_font_weight' => 400,
            'button_border_radius' => 5,
            'button_border_width' => 2,
            'badge_font_size' => 12,
            'badge_line_height' => 12,
            'badge_font_weight' => 400,
            'badge_border_radius' => 5,
            'badge_border_width' => 1,
            'tx_color' => '#000000',
            'bg_color' => '#ffffff',
            'spacer' => 4,
            'header_height' => 80,
        ];
    }

    public static function cssInputRow(): array
    {
        return [
            'tx_color' => '#000000',
            'bg_color' => '#ffffff',
            'disabled_bg_color' => '#eeeeee',
            'label_color' => '#626262',
            'label_color_focus' => '#626262',
            'label_weight' => 300,
            'label_weight_focus' => 400,
            'select_hover' => '#f6f6f6',
            'border_color' => '#DEDEDE',
            'border_color_focus' => '#626262',
            'border_radius' => 5,
            'border_top' => 1,
            'border_right' => 1,
            'border_bottom' => 1,
            'border_left' => 1,
            'date_default' => '#DEDEDE',
            'date_active' => '#626262',
            'date_bg' => '#f6f6f6',
            'date_bg_hover' => '#eeeeee',
            'date_border_radius' => 5,
            'dropdown_tx_color' => '#000000',
            'dropdown_bg_color' => '#ffffff',
        ];
    }

    public static function cssModalRow(): array
    {
        return [
            'tx' => '#000000',
            'bg' => '#ffffff',
            'border_color' => '#DEDEDE',
            'border_width' => 1,
            'border_radius' => 5,
        ];
    }

    public static function cssDropdownRow(): array
    {
        return [
            'tx' => '#000000',
            'bg' => '#ffffff',
            'bg_hover' => 'rgba(0,0,0,.02)',
            'border_color' => '#DEDEDE',
            'border_width' => 1,
            'border_radius' => 5,
        ];
    }

    public static function cssAlertRow(): array
    {
        return [
            'tx' => 'var(--dark-color)',
            'bg' => 'var(--light-color)',
            'top' => 'calc(var(--spacer) * 5)',
            'right' => 'calc(var(--spacer) * 5)',
            'border_color' => '#DEDEDE',
            'border_width' => 1,
            'border_radius' => 5,
        ];
    }

    public static function mergeRecordDefaults(?object $record, array $defaults): object
    {
        return (object) array_merge($defaults, is_object($record) ? get_object_vars($record) : []);
    }
}
