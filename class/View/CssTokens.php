<?php

namespace Wonder\View;

use Wonder\App\RuntimeDefaults;
use Wonder\App\SeedDefaults;
use Wonder\App\Support\CssFontFamily;

/**
 * Il foglio di stile dei design token del sito (`set-up/root.css`) e le classi
 * colore (`set-up/color.css`), scritti a partire dalle righe di configurazione.
 *
 * Le funzioni legacy `cssRoot()` / `cssColor()` leggono le righe dal database e
 * delegano qui; il catalogo dei componenti (`Wonder\Docs`) usa invece
 * `defaultRoot()` per dare al tema Wonder dei token coerenti anche senza un
 * sito. Un solo template, nessuna copia della lista delle variabili.
 */
final class CssTokens
{
    /**
     * Il blocco `:root { ... }` con tutte le variabili del tema Wonder.
     *
     * @param array<int, array<string, mixed>> $colors righe `css_color` (`var`, `color`, `contrast`)
     * @param array<string, string> $fontFamilies chiavi `default`, `title_big`, `title`, `subtitle`, `text`, `text_small`
     */
    public static function root(
        object $default,
        object $input,
        object $auth,
        object $modal,
        object $dropdown,
        object $alert,
        array $colors,
        array $fontFamilies,
        string $defaultImage = ''
    ): string {
        $fontFamilyDefault = (string) ($fontFamilies['default'] ?? '');
        $fontFamilyTitleBig = (string) ($fontFamilies['title_big'] ?? $fontFamilyDefault);
        $fontFamilyTitle = (string) ($fontFamilies['title'] ?? $fontFamilyDefault);
        $fontFamilySubtitle = (string) ($fontFamilies['subtitle'] ?? $fontFamilyDefault);
        $fontFamilyText = (string) ($fontFamilies['text'] ?? $fontFamilyDefault);
        $fontFamilyTextSmall = (string) ($fontFamilies['text_small'] ?? $fontFamilyDefault);

        $RETURN = ":root {\n";
        $RETURN .= "\n";
        $RETURN .= "--spacer: {$default->spacer}px;\n";
        $RETURN .= "--header-height: {$default->header_height}px;\n";
        $RETURN .= "--default-image: url('".$defaultImage."');\n";
        $RETURN .= "\n";
        $RETURN .= "/* Default font */\n";
        $RETURN .= "--font-family: $fontFamilyDefault;\n";
        $RETURN .= "--font-weight: {$default->font_weight};\n";
        $RETURN .= "--font-size: {$default->font_size}px;\n";
        $RETURN .= "--line-height: {$default->line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font titolo grande */\n";
        $RETURN .= "--title-big-font-family: $fontFamilyTitleBig;\n";
        $RETURN .= "--title-big-font-weight: {$default->title_big_font_weight};\n";
        $RETURN .= "--title-big-font-size: {$default->title_big_font_size}px;\n";
        $RETURN .= "--title-big-line-height: {$default->title_big_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font titolo */\n";
        $RETURN .= "--title-font-family: $fontFamilyTitle;\n";
        $RETURN .= "--title-font-weight: {$default->title_font_weight};\n";
        $RETURN .= "--title-font-size: {$default->title_font_size}px;\n";
        $RETURN .= "--title-line-height: {$default->title_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font sottotitolo */\n";
        $RETURN .= "--subtitle-font-family: $fontFamilySubtitle;\n";
        $RETURN .= "--subtitle-font-weight: {$default->subtitle_font_weight};\n";
        $RETURN .= "--subtitle-font-size: {$default->subtitle_font_size}px;\n";
        $RETURN .= "--subtitle-line-height: {$default->subtitle_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font testo */\n";
        $RETURN .= "--text-font-family: $fontFamilyText;\n";
        $RETURN .= "--text-font-weight: {$default->text_font_weight};\n";
        $RETURN .= "--text-font-size: {$default->text_font_size}px;\n";
        $RETURN .= "--text-line-height: {$default->text_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Font testo piccolo */\n";
        $RETURN .= "--text-small-font-family: $fontFamilyTextSmall;\n";
        $RETURN .= "--text-small-font-weight: {$default->text_small_font_weight};\n";
        $RETURN .= "--text-small-font-size: {$default->text_small_font_size}px;\n";
        $RETURN .= "--text-small-line-height: {$default->text_small_line_height}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up bottoni */\n";
        $RETURN .= "--button-font-weight: $default->button_font_weight;\n";
        $RETURN .= "--button-font-size: {$default->button_font_size}px;\n";
        $RETURN .= "--button-line-height: {$default->button_line_height}px;\n";
        $RETURN .= "--button-border-radius: {$default->button_border_radius}px;\n";
        $RETURN .= "--button-border-width: {$default->button_border_width}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up badge */\n";
        $RETURN .= "--badge-font-weight: $default->badge_font_weight;\n";
        $RETURN .= "--badge-font-size: {$default->badge_font_size}px;\n";
        $RETURN .= "--badge-line-height: {$default->badge_line_height}px;\n";
        $RETURN .= "--badge-border-radius: {$default->badge_border_radius}px;\n";
        $RETURN .= "--badge-border-width: {$default->badge_border_width}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up input */\n";
        $RETURN .= "--input-tx-color: $input->tx_color;\n";
        $RETURN .= "--input-tx-family: var(--font-family);\n";
        $RETURN .= "--input-tx-weight: var(--font-weight);\n";
        $RETURN .= "\n";
        $RETURN .= "--input-bg-color: $input->bg_color;\n";
        $RETURN .= "--input-disabled-bg: $input->disabled_bg_color;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-dropdown-tx-color: $input->dropdown_tx_color;\n";
        $RETURN .= "--input-dropdown-bg-color: $input->dropdown_bg_color;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-select-hover: $input->select_hover;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-border-color: $input->border_color;\n";
        $RETURN .= "--input-border-focus: $input->border_color_focus;\n";
        $RETURN .= "--input-border-radius: {$input->border_radius}px;\n";
        $RETURN .= "--input-border-top: {$input->border_top}px;\n";
        $RETURN .= "--input-border-right: {$input->border_right}px;\n";
        $RETURN .= "--input-border-bottom: {$input->border_bottom}px;\n";
        $RETURN .= "--input-border-left: {$input->border_left}px;\n";
        $RETURN .= "\n";
        $RETURN .= "--input-date-default: $input->date_default;\n";
        $RETURN .= "--input-date-active: $input->date_active;\n";
        $RETURN .= "--input-date-bg: $input->date_bg;\n";
        $RETURN .= "--input-date-bg-hover: $input->date_bg_hover;\n";
        $RETURN .= "--input-date-border-radius: {$input->date_border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up input label */\n";
        $RETURN .= "--input-label-color: $input->label_color;\n";
        $RETURN .= "--input-label-focus-color: $input->label_color_focus;\n";
        $RETURN .= "--input-label-weight: $input->label_weight;\n";
        $RETURN .= "--input-label-focus-weight: $input->label_weight_focus;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up autenticazione */\n";
        $RETURN .= "--auth-bg-color: $auth->bg_color;\n";
        $RETURN .= "--auth-tx-color: $auth->tx_color;\n";
        $RETURN .= "--auth-form-bg-color: $auth->form_bg_color;\n";
        $RETURN .= "--auth-form-tx-color: $auth->form_tx_color;\n";
        $RETURN .= "--auth-form-border-color: $auth->form_border_color;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up modal */\n";
        $RETURN .= "--modal-tx: $modal->tx;\n";
        $RETURN .= "--modal-bg: $modal->bg;\n";
        $RETURN .= "--modal-border-color: $modal->border_color;\n";
        $RETURN .= "--modal-border-width: {$modal->border_width}px;\n";
        $RETURN .= "--modal-border-radius: {$modal->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up dropdown */\n";
        $RETURN .= "--dropdown-tx: $dropdown->tx;\n";
        $RETURN .= "--dropdown-bg: $dropdown->bg;\n";
        $RETURN .= "--dropdown-bg-hover: $dropdown->bg_hover;\n";
        $RETURN .= "--dropdown-border-color: $dropdown->border_color;\n";
        $RETURN .= "--dropdown-border-width: {$dropdown->border_width}px;\n";
        $RETURN .= "--dropdown-border-radius: {$dropdown->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up alert */\n";
        $RETURN .= "--alert-tx: $alert->tx;\n";
        $RETURN .= "--alert-bg: $alert->bg;\n";
        $RETURN .= "--alert-top: $alert->top;\n";
        $RETURN .= "--alert-right: $alert->right;\n";
        $RETURN .= "--alert-border-color: $alert->border_color;\n";
        $RETURN .= "--alert-border-width: {$alert->border_width}px;\n";
        $RETURN .= "--alert-border-radius: {$alert->border_radius}px;\n";
        $RETURN .= "\n";
        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori */\n";

        foreach ($colors as $row) {
            $row = (array) $row;
            $var = (string) ($row['var'] ?? '');
            $colorHEX = (string) ($row['color'] ?? '');
            $colorRGB = self::hexToRgb($colorHEX);
            $contrastHEX = (string) ($row['contrast'] ?? '');
            $contrastRGB = self::hexToRgb($contrastHEX);

            $RETURN .= "\n";
            $RETURN .= "/* $var */\n";
            $RETURN .= "--$var-color: $colorHEX;\n";
            $RETURN .= "--$var-o-color: $contrastHEX;\n";
            $RETURN .= "--$var-color-rgb: $colorRGB;\n";
            $RETURN .= "--$var-o-color-rgb: $contrastRGB;\n";

            for ($i = 0; $i < 11; $i++) {
                $opacity = $i * 10;
                $opacityCSS = $i / 10;

                $RETURN .= "--$var-color-$opacity: rgba(var(--$var-color-rgb), $opacityCSS);\n";
                $RETURN .= "--$var-o-color-$opacity: rgba(var(--$var-o-color-rgb), $opacityCSS);\n";
            }
        }

        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori testo */\n";
        $RETURN .= "--tx-color: ".$default->tx_color.";\n";
        $RETURN .= "--tx-color-rgb: ".self::hexToRgb((string) $default->tx_color).";\n";

        for ($i = 0; $i < 11; $i++) {
            $opacity = $i * 10;
            $opacityCSS = $i / 10;

            $RETURN .= "--tx-color-$opacity: rgba(var(--tx-color-rgb), $opacityCSS);\n";
        }

        $RETURN .= "\n";
        $RETURN .= "/* Set-up colori sfondo */\n";
        $RETURN .= "--bg-color: ".$default->bg_color.";\n";
        $RETURN .= "--bg-color-rgb: ".self::hexToRgb((string) $default->bg_color).";\n";

        for ($i = 0; $i < 11; $i++) {
            $opacity = $i * 10;
            $opacityCSS = $i / 10;

            $RETURN .= "--bg-color-$opacity: rgba(var(--bg-color-rgb), $opacityCSS);\n";
        }

        $RETURN .= "\n";
        $RETURN .= "}";

        return $RETURN;
    }

    /**
     * Le classi utility `tx-*`, `bg-*`, `btn-*`, `badge-*` per ogni colore.
     *
     * @param array<int, array<string, mixed>> $colors righe `css_color`
     */
    public static function colorClasses(array $colors): string
    {
        $RETURN = "/* Classi colori */\n";

        foreach ($colors as $row) {
            $row = (array) $row;
            $var = (string) ($row['var'] ?? '');

            $RETURN .= "\n";
            $RETURN .= "/* $var */\n";

            $RETURN .= ".tx-$var { color: var(--$var-color) !important; }\n";
            $RETURN .= ".hover\:tx-$var:hover { color: var(--$var-color) !important; }\n";
            $RETURN .= ".tx-$var-o { color: var(--$var-o-color) !important; }\n";
            $RETURN .= ".hover\:tx-$var-o { color: var(--$var-o-color) !important; }\n";

            $RETURN .= ".bg-$var { background: var(--$var-color) !important; }\n";
            $RETURN .= ".hover\:bg-$var:hover { color: var(--$var-color) !important; }\n";
            $RETURN .= ".bg-$var-o { background: var(--$var-o-color) !important; }\n";
            $RETURN .= ".hover\:bg-$var-o { background: var(--$var-o-color) !important; }\n";

            $RETURN .= ".tx-stroke-$var { -webkit-text-stroke-color: var(--$var-color); }\n";

            for ($i = 0; $i < 11; $i++) {
                $opacity = $i * 10;

                $RETURN .= ".bg-$var-$opacity { background: var(--$var-color-$opacity) !important; }\n";
                $RETURN .= ".bg-$var-o-$opacity { background: var(--$var-o-color-$opacity) !important; }\n";
                $RETURN .= ".hover\:bg-$var-$opacity { background: var(--$var-color-$opacity) !important; }\n";
                $RETURN .= ".hover\:bg-$var-o-$opacity { background: var(--$var-o-color-$opacity) !important; }\n";
            }

            $RETURN .= "\n";
            $RETURN .= ".badge.badge-$var, .btn.btn-$var { border-color: var(--$var-color-100); background: var(--$var-color-100); color: var(--$var-o-color); }\n";
            $RETURN .= ".badge.badge-$var-o, .btn.btn-$var-o { border-color: var(--$var-color); background: var(--$var-color-0); color: var(--$var-color-100); }\n";
            $RETURN .= ".btn.btn-$var:hover { background: var(--$var-color-90); }\n";
            $RETURN .= ".btn.btn-$var-o:hover { background: var(--$var-color-10); }\n";
        }

        return $RETURN;
    }

    /**
     * I token di un sito appena installato: le righe seed di `SeedDefaults`,
     * la palette di `RuntimeDefaults` e il font di fallback. Servono al
     * catalogo dei componenti per rendere il tema Wonder senza database.
     */
    public static function defaultRoot(): string
    {
        $font = CssFontFamily::fallback();

        return self::root(
            (object) SeedDefaults::cssDefaultRow(),
            (object) SeedDefaults::cssInputRow(),
            (object) SeedDefaults::cssAuthRow(),
            (object) SeedDefaults::cssModalRow(),
            (object) SeedDefaults::cssDropdownRow(),
            (object) SeedDefaults::cssAlertRow(),
            RuntimeDefaults::defaultColors(),
            [
                'default' => $font,
                'title_big' => $font,
                'title' => $font,
                'subtitle' => $font,
                'text' => $font,
                'text_small' => $font,
            ]
        );
    }

    /** Le classi colore della palette di default, accanto a `defaultRoot()`. */
    public static function defaultColorClasses(): string
    {
        return self::colorClasses(RuntimeDefaults::defaultColors());
    }

    /** Stessa regola della funzione globale `hexToRgb()`: `#abc` e `#aabbcc`, altrimenti nero. */
    private static function hexToRgb(string $hex): string
    {
        $hex = trim($hex);

        if ($hex === '') {
            return '0, 0, 0';
        }

        if (preg_match('/^#([a-f0-9]{3})$/i', $hex, $matches) === 1) {
            $hex = '#'.$matches[1][0].$matches[1][0].$matches[1][1].$matches[1][1].$matches[1][2].$matches[1][2];
        }

        if (preg_match('/^#([a-f0-9]{6})$/i', $hex) !== 1) {
            return '0, 0, 0';
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return "$r, $g, $b";
    }
}
