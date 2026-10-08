<?php

    function cssRoot($update = true) {

        global $DEFAULT;
        global $ROOT;

        $PATH = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/root.css';

        $CSS_DEFAULT = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_default', 'id', '1'), \Wonder\App\SeedDefaults::cssDefaultRow());
        $CSS_INPUT = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_input', 'id', '1'), \Wonder\App\SeedDefaults::cssInputRow());
        $CSS_AUTH = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_auth', 'id', '1'), \Wonder\App\SeedDefaults::cssAuthRow());

        $CSS_MODAL = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_modal', 'id', '1'), \Wonder\App\SeedDefaults::cssModalRow());
        $CSS_DROPDOWN = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_dropdown', 'id', '1'), \Wonder\App\SeedDefaults::cssDropdownRow());
        $CSS_ALERT = \Wonder\App\SeedDefaults::mergeRowDefaults(info('css_alert', 'id', '1'), \Wonder\App\SeedDefaults::cssAlertRow());

        $decodeFontFamily = static function ($fontId): string {
            $fontFamily = (string) (info('css_font', 'id', $fontId)->font_family ?? '');

            return \Wonder\App\Support\CssFontFamily::normalize(
                $fontFamily,
                \Wonder\App\Support\CssFontFamily::fallback()
            );
        };

        // Il template delle variabili sta in CssTokens: lo stesso usato dal
        // catalogo dei componenti per i token di default.
        $RETURN = \Wonder\View\CssTokens::root(
            $CSS_DEFAULT,
            $CSS_INPUT,
            $CSS_AUTH,
            $CSS_MODAL,
            $CSS_DROPDOWN,
            $CSS_ALERT,
            sqlSelect('css_color')->row,
            [
                'default' => $decodeFontFamily($CSS_DEFAULT->font_id),
                'title_big' => $decodeFontFamily($CSS_DEFAULT->title_big_font_id),
                'title' => $decodeFontFamily($CSS_DEFAULT->title_font_id),
                'subtitle' => $decodeFontFamily($CSS_DEFAULT->subtitle_font_id),
                'text' => $decodeFontFamily($CSS_DEFAULT->text_font_id),
                'text_small' => $decodeFontFamily($CSS_DEFAULT->text_small_font_id),
            ],
            (string) ($DEFAULT->image ?? '')
        );

        if ($update) {
            
            $FILE = fopen($PATH, "w");
            fwrite($FILE, $RETURN);
            fclose($FILE);
            
        } else {

            return $RETURN;

        }

    }

    function cssColor($update = true) {

        global $ROOT;

        $PATH = $ROOT.'/assets/'.$_ENV['ASSETS_VERSION'].'/css/set-up/color.css';

        $RETURN = \Wonder\View\CssTokens::colorClasses(sqlSelect('css_color')->row);

        if ($update) {
            
            $FILE = fopen($PATH, "w");
            fwrite($FILE, $RETURN);
            fclose($FILE);
            
        } else {

            return $RETURN;

        }

    }
