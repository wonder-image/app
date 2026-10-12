<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\App\Credentials;
use Wonder\App\Security\RecaptchaGuard;
use Wonder\Themes\Support\PageAssets;
use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder dell'Element `reCAPTCHA`.
 *
 * Output:
 *  - `<div class="g-recaptcha" data-wi-…>` (widget Google; con
 *    `data-wi-score-key` la lib usa la v3 in background e accende la casella
 *    v2 solo su richiesta del server, `data-wi-challenge="1"` o risposta 618)
 *  - `<input type="hidden" name="g-recaptcha-token">` e
 *    `<input type="hidden" name="g-recaptcha-action">` (riempiti dal JS;
 *    `required` solo quando la casella è visibile da subito)
 *  - `<input type="hidden" name="g-recaptcha-type">` (`score` o `checkbox`:
 *    sceglie la chiave lato server)
 *  - con la v3 e il badge nascosto, la riga di attribuzione di Google.
 *
 * Le chiavi e il badge sono credenziali globali del sito
 * (`\Wonder\App\Credentials::api()`), non parametri per chiamata.
 */
class reCAPTCHA extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $api = Credentials::api();
        $siteKey = trim((string) ($api->g_recaptcha_site_key ?? ''));
        $scoreKey = trim((string) ($api->g_recaptcha_score_site_key ?? ''));
        $badge = strtolower(trim((string) ($api->g_recaptcha_badge ?? 'true'))) !== 'false';
        $action = (string) ($this->schema['recaptcha_action'] ?? 'submit');
        $challenge = $scoreKey !== '' && $siteKey !== '' && RecaptchaGuard::challenged($action);

        return self::cellStyle() . self::markup($this->schema, $siteKey, $scoreKey, $badge, $challenge);
    }

    /**
     * Con la sola v3 il riquadro resta vuoto, ma la cella della griglia che lo
     * contiene prende comunque il gap del form. La cella marcata con
     * `wi-recaptcha-cell` esce dal flusso finché il riquadro è vuoto e torna da
     * sola quando compare la casella o la riga di attribuzione. Non usa
     * `display:none`: la lib carica Google quando il riquadro entra nel
     * viewport, e un elemento senza box non ci entra mai.
     */
    private static function cellStyle(): string
    {
        return PageAssets::once('wi-recaptcha-cell', '<style data-wi-recaptcha-style>.wi-recaptcha-cell:has(.g-recaptcha:empty):not(:has(.wi-recaptcha-terms)){position:absolute;width:0;height:0;margin:0;padding:0}</style>');
    }

    /** Markup del riquadro, senza credenziali né sessione (testabile). */
    public static function markup(array $schema, string $siteKey, string $scoreKey, bool $badge, bool $challenge): string
    {
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $score = $scoreKey !== '';
        $open = $challenge && $score && $siteKey !== '';
        $required = !$score || $open ? ' required' : '';
        $type = $score && !$open ? 'score' : 'checkbox';

        return '<div class="g-recaptcha" data-wi-site-key="'.$e($siteKey).'"'
            .($score ? ' data-wi-score-key="'.$e($scoreKey).'"' : '')
            .' data-wi-badge="'.($badge ? 'true' : 'false').'"'
            .($open ? ' data-wi-challenge="1"' : '')
            .' data-wi-theme="'.$e($schema['recaptcha_theme'] ?? 'light').'"'
            .' data-wi-size="'.$e($schema['recaptcha_size'] ?? 'normal').'"'
            .' data-wi-action="'.$e($schema['recaptcha_action'] ?? 'submit').'"></div>'
            .'<input type="hidden" name="g-recaptcha-token"'.$required.'>'
            .'<input type="hidden" name="g-recaptcha-action"'.$required.'>'
            .'<input type="hidden" name="g-recaptcha-type" value="'.$type.'">'
            .(!$badge && $score ? self::terms() : '');
    }

    /** Riga di attribuzione di Google (obbligatoria quando il badge v3 è nascosto). */
    private static function terms(): string
    {
        $link = static fn (string $href, string $text): string => '<a href="'.$href.'" target="_blank" rel="noopener">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</a>';

        $notice = htmlspecialchars(self::text('components.forms.recaptcha.notice', 'Questo sito è protetto da reCAPTCHA: si applicano le {{privacy}} e i {{terms}} di Google.'), ENT_QUOTES, 'UTF-8');

        return '<p class="wi-recaptcha-terms">'.strtr($notice, [
            '{{privacy}}' => $link('https://policies.google.com/privacy', self::text('components.forms.recaptcha.privacy', 'Norme sulla privacy')),
            '{{terms}}' => $link('https://policies.google.com/terms', self::text('components.forms.recaptcha.terms', 'Termini di servizio')),
        ]).'</p>';
    }

    private static function text(string $key, string $fallback): string
    {
        $value = '';

        if (function_exists('__t')) {
            try {
                $value = trim((string) __t($key));
            } catch (\Throwable) {
                // chiave mancante (components.json vecchio) o provider non inizializzato: ricade sul fallback
            }
        }

        return $value !== '' && $value !== $key ? $value : $fallback;
    }
}
