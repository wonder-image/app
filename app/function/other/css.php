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

        // Colori e famiglie servono sia a root.css sia a docs/ai/DESIGN.md.
        $colors = sqlSelect('css_color')->row;
        $fontFamilies = [
            'default' => $decodeFontFamily($CSS_DEFAULT->font_id),
            'title_big' => $decodeFontFamily($CSS_DEFAULT->title_big_font_id),
            'title' => $decodeFontFamily($CSS_DEFAULT->title_font_id),
            'subtitle' => $decodeFontFamily($CSS_DEFAULT->subtitle_font_id),
            'text' => $decodeFontFamily($CSS_DEFAULT->text_font_id),
            'text_small' => $decodeFontFamily($CSS_DEFAULT->text_small_font_id),
        ];

        // Il template delle variabili sta in CssTokens: lo stesso usato dal
        // catalogo dei componenti per i token di default.
        $RETURN = \Wonder\View\CssTokens::root(
            $CSS_DEFAULT,
            $CSS_INPUT,
            $CSS_AUTH,
            $CSS_MODAL,
            $CSS_DROPDOWN,
            $CSS_ALERT,
            $colors,
            $fontFamilies,
            (string) ($DEFAULT->image ?? '')
        );

        if ($update) {

            $FILE = fopen($PATH, "w");
            fwrite($FILE, $RETURN);
            fclose($FILE);

            // Solo in sviluppo: docs/ai/DESIGN.md è la proiezione dei token per
            // gli agenti AI. Deriva dal DB come root.css ed è gitignorato nel
            // sito. Il gate è Debug::enabled(): APP_DEBUG=true, oppure richiesta
            // HTTP da host locale quando APP_DEBUG non è attivo (da CLI conta
            // solo APP_DEBUG). In produzione, con APP_DEBUG=false e richieste
            // non da loopback, il file non viene scritto.
            if (\Wonder\App\Debug::enabled()) {
                cssDesignMd($ROOT, $CSS_DEFAULT, $colors, $fontFamilies);
            }

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

    /**
     * Scrive docs/ai/DESIGN.md, la proiezione Markdown dei token per gli agenti AI.
     * Chiamata da cssRoot() quando Debug::enabled() è vero. Un errore di scrittura
     * (cartella non creabile, file non scrivibile) finisce nel log e non blocca il
     * salvataggio del CSS: renderDebug è false perché in debug Logger::log()
     * stamperebbe l'errore e farebbe exit() nel mezzo di un salvataggio dal backend.
     */
    function cssDesignMd(string $root, object $default, array $colors, array $fontFamilies): void {

        $DIR = $root.'/docs/ai';
        $PATH = $DIR.'/DESIGN.md';

        try {

            if (!is_dir($DIR) && !@mkdir($DIR, 0777, true) && !is_dir($DIR)) {
                throw new \RuntimeException('Cartella non creabile: '.$DIR);
            }

            $markdown = \Wonder\View\CssTokens::designMd($default, $colors, $fontFamilies);

            if (@file_put_contents($PATH, $markdown) === false) {
                throw new \RuntimeException('File non scrivibile: '.$PATH);
            }

        } catch (\Throwable $exception) {

            \Wonder\App\Logger::log($exception, 'css', 'design-md', 'WARNING', 'error', ['path' => $PATH], false);

        }

    }
