<?php

namespace Wonder\App;

class SeedDefaults
{
    public static function adminUsername(): string
    {
        return 'admin';
    }

    public static function adminPassword(): string
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

    public static function analyticsRow(): array
    {
        return [
            'tag_manager' => '',
            'active_tag_manager' => 'false',
            'pixel_facebook' => '',
            'active_pixel_facebook' => 'false',
        ];
    }

    public static function securityRow(string $apiKey = ''): array
    {
        return [
            'api_key' => $apiKey,
            'mail_service' => 'phpmailer',
        ];
    }

    public static function seoRow(): array
    {
        return [
            'author' => 'Andrea Marinoni',
            'copyright' => 'Wonder Image',
            'creator' => 'wonderimage',
            'reply' => 'marinoni@wonderimage.it',
        ];
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

    public static function mergeRowDefaults(?object $record, array $defaults): object
    {
        return (object) array_merge($defaults, is_object($record) ? get_object_vars($record) : []);
    }
}
