<?php

namespace Wonder\App\Support\Mail;

/**
 * Layout `basic` delle mail: tabelle centrate a 600px, logo, colore primario
 * del sito (`css_color`), tema scuro e piè di pagina con i dati della società.
 *
 * Due tipi di corpo:
 * - `MailMessage` (inizia con `MailMessage::MARKER`): markup già escapato,
 *   entra così com'è e il preheader sale in cima;
 * - stringa HTML scritta a mano (moduli e siti): passa da `sanitizeEcho()`
 *   come sempre e riceve solo carattere, colore e interlinea del contenitore.
 *
 * Non lancia mai: colori, traduzioni e società mancanti hanno un fallback,
 * perché una mail non deve fallire per un dato di contorno.
 */
final class MailLayout
{

    public const WIDTH = 600;

    private const FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    private const FALLBACK_PRIMARY = '#18181b';

    /** @var array{primary: string, contrast: string}|null */
    private static ?array $brand = null;

    public static function render(string $body, string $template = 'basic'): string
    {
        if ($template !== 'basic') {
            return '';
        }

        $preheader = '';
        // Un corpo scritto a mano può avere colori inline scuri: resta in tema chiaro.
        $built = MailMessage::isBuilt($body);
        $scheme = $built ? 'light dark' : 'light';

        if ($built) {
            [$body, $preheader] = self::extractPreheader(substr($body, strlen(MailMessage::MARKER)));
        } elseif (function_exists('sanitizeEcho')) {
            $body = sanitizeEcho($body);
        }

        $brand = self::brand();
        $society = self::society();
        $lang = self::escape(self::lang());
        $title = self::escape($society['name']);

        return '<!DOCTYPE html>'
            .'<html lang="'.$lang.'" xmlns="http://www.w3.org/1999/xhtml">'
            .'<head>'
            .'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            .'<meta name="x-apple-disable-message-reformatting">'
            .'<meta name="color-scheme" content="'.$scheme.'">'
            .'<meta name="supported-color-schemes" content="'.$scheme.'">'
            .'<title>'.$title.'</title>'
            .'<style type="text/css">'.self::css($brand, $built).'</style>'
            .'</head>'
            .'<body class="wi-mail-bg" style="margin:0;padding:0;width:100%;background-color:#f4f4f5;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">'
            .self::preheaderHtml($preheader)
            .'<table role="presentation" class="wi-mail-bg" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#f4f4f5;">'
            .'<tr><td align="center" style="padding:32px 12px;">'
            .'<table role="presentation" class="wi-mail-container" width="'.self::WIDTH.'" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:'.self::WIDTH.'px;">'
            .self::header($society, $brand)
            // Con `border-collapse:collapse` il bordo di una cella ignora
            // `border-radius`: la card è una tabella separata a sé.
            .'<tr><td>'
            .'<table role="presentation" class="wi-mail-card" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate;background-color:#ffffff;border:1px solid #e4e4e7;border-top:3px solid '.$brand['primary'].';border-radius:0 0 12px 12px;"><tr>'
            .'<td class="wi-mail-body" style="padding:36px 40px 20px;font-family:'.self::FONT.';font-size:15px;line-height:1.6;color:#3f3f46;word-break:break-word;">'
            .$body
            .'</td></tr></table>'
            .'</td></tr>'
            .self::footer($society)
            .'</table>'
            .'</td></tr></table>'
            .'</body></html>';
    }

    /**
     * Colore primario e colore del testo sopra di esso, dalla tabella
     * `css_color`. Un primario troppo chiaro per stare su sfondo bianco
     * diventa un grigio scuro.
     *
     * @return array{primary: string, contrast: string}
     */
    public static function brand(): array
    {
        if (self::$brand !== null) {
            return self::$brand;
        }

        $primary = self::FALLBACK_PRIMARY;
        $contrast = '#ffffff';

        try {
            if (function_exists('sqlSelect')) {
                foreach ((array) (sqlSelect('css_color', ['var' => 'primary'], 1)->row ?? []) as $row) {
                    $row = (array) $row;
                    $primary = self::hex((string) ($row['color'] ?? '')) ?? $primary;
                    $contrast = self::hex((string) ($row['contrast'] ?? '')) ?? $contrast;
                }
            }
        } catch (\Throwable) {
            // Senza database (CLI, test, primo avvio) resta il fallback.
        }

        return self::$brand = self::readable($primary, $contrast);
    }

    /**
     * Colori fissati dal sito o dai test; `null` torna alla lettura da
     * `css_color`.
     */
    public static function useBrand(?string $primary, ?string $contrast = null): void
    {
        if ($primary === null) {
            self::$brand = null;

            return;
        }

        self::$brand = self::readable(
            self::hex($primary) ?? self::FALLBACK_PRIMARY,
            self::hex((string) $contrast) ?? '#ffffff'
        );
    }

    /** Traduzione con fallback: una chiave mancante non arriva mai nella mail. */
    public static function translate(string $key, string $fallback): string
    {
        try {
            if (function_exists('__t')) {
                $value = __t($key);

                if (is_string($value) && trim($value) !== '' && $value !== $key) {
                    return $value;
                }
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    /** @return array{0: string, 1: string} corpo senza preheader, testo del preheader */
    private static function extractPreheader(string $body): array
    {
        $pattern = '#<div [^>]*data-wi-mail-preheader[^>]*>(.*?)</div>#s';

        if (preg_match($pattern, $body, $match) !== 1) {
            return [$body, ''];
        }

        return [(string) preg_replace($pattern, '', $body, 1), $match[1]];
    }

    private static function preheaderHtml(string $preheader): string
    {
        if ($preheader === '') {
            return '';
        }

        // Gli spazi invisibili evitano che il client riempia l'anteprima
        // con le prime righe del corpo.
        return '<div '.MailMessage::TEXT_SKIP.' style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;opacity:0;">'
            .$preheader.str_repeat('&#847;&zwnj;&nbsp;', 40).'</div>';
    }

    /**
     * Logo del sito; senza logo, il nome preceduto da un quadrato nel colore
     * primario.
     *
     * @param array<string, string> $society
     * @param array{primary: string, contrast: string} $brand
     */
    private static function header(array $society, array $brand): string
    {
        $name = self::escape($society['name']);
        $logo = self::logo('logo');
        $logoWhite = $logo === null ? null : self::logo('logoWhite');

        if ($logo === null) {
            $content = '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                .'<td class="wi-mail-mark" width="28" height="28" bgcolor="'.$brand['primary'].'" style="width:28px;height:28px;border-radius:6px;background-color:'.$brand['primary'].';font-size:1px;line-height:1px;">&nbsp;</td>'
                .'<td style="padding-left:10px;"><span class="wi-mail-heading" style="font-family:'.self::FONT.';font-size:20px;line-height:28px;font-weight:700;color:#18181b;">'.$name.'</span></td>'
                .'</tr></table>';
        } else {
            $content = '<img class="wi-mail-logo" src="'.self::escape($logo).'" alt="'.$name.'" height="40" style="display:block;height:40px;width:auto;max-width:220px;border:0;outline:none;text-decoration:none;">';

            if ($logoWhite !== null) {
                $content .= '<!--[if !mso]><!--><img class="wi-mail-logo-dark" src="'.self::escape($logoWhite).'" alt="'.$name.'" height="40" style="display:none;height:40px;width:auto;max-width:220px;border:0;outline:none;text-decoration:none;"><!--<![endif]-->';
            }
        }

        $url = $society['url'];

        if ($url !== '') {
            $content = '<a href="'.self::escape($url).'" target="_blank" rel="noopener" style="display:inline-block;text-decoration:none;">'.$content.'</a>';
        }

        return '<tr><td align="left" style="padding:0 8px 20px;">'.$content.'</td></tr>';
    }

    /** @param array<string, string> $society */
    private static function footer(array $society): string
    {
        $lines = [];

        if ($society['legal'] !== '') {
            $lines[] = '<strong style="font-weight:600;">'.self::escape($society['legal']).'</strong>';
        }

        foreach (['address', 'fiscal'] as $key) {
            if ($society[$key] !== '') {
                $lines[] = self::escape($society[$key]);
            }
        }

        $contacts = [];

        if ($society['email'] !== '') {
            $email = self::escape($society['email']);
            $contacts[] = '<a href="mailto:'.$email.'" style="color:#71717a;text-decoration:underline;">'.$email.'</a>';
        }

        if ($society['tel'] !== '') {
            $contacts[] = self::escape($society['tel']);
        }

        if ($contacts !== []) {
            $lines[] = implode(' &middot; ', $contacts);
        }

        $rights = self::translate('emails.layout.rights', 'Tutti i diritti riservati.');
        $owner = rtrim($society['legal'] !== '' ? $society['legal'] : $society['name'], '. ');
        $lines[] = '&copy; '.date('Y').' '.self::escape($owner.'. '.$rights);

        return '<tr><td class="wi-mail-muted" align="center" style="padding:24px 24px 0;font-family:'.self::FONT.';font-size:12px;line-height:1.6;color:#71717a;">'
            .implode('<br>', $lines)
            .'</td></tr>';
    }

    /** @param array{primary: string, contrast: string} $brand */
    private static function css(array $brand, bool $dark = true): string
    {
        $scheme = $dark ? 'light dark' : 'light';

        // In tema scuro un primario scuro sparisce sul fondo: pulsante chiaro.
        $darkButton = self::luminance($brand['primary']) < 0.12
            ? '.wi-mail-button,.wi-mail-mark{background-color:#fafafa!important}.wi-mail-button-link{color:#18181b!important}'
            : '';

        $darkRules = '.wi-mail-bg{background-color:#09090b!important}'
            .'.wi-mail-card{background-color:#18181b!important;border-right-color:#27272a!important;border-bottom-color:#27272a!important;border-left-color:#27272a!important}'
            .'.wi-mail-body,.wi-mail-detail-row,.wi-mail-heading{color:#f4f4f5!important}'
            .'.wi-mail-detail{background-color:#27272a!important}'
            .'.wi-mail-detail-row,.wi-mail-items,.wi-mail-item-row,.wi-mail-total{border-color:#3f3f46!important}'
            .'.wi-mail-item-placeholder{background-color:#27272a!important}'
            .'.wi-mail-sale{color:#f87171!important}'
            .'.wi-mail-rule{background-color:#3f3f46!important}'
            .'.wi-mail-muted,.wi-mail-muted a{color:#a1a1aa!important}'
            .'.wi-mail-body a:not(.wi-mail-button-link){color:#f4f4f5!important}'
            .'.wi-mail-logo-dark{display:block!important}'
            .'.wi-mail-logo-dark+.wi-mail-logo,.wi-mail-logo:has(+.wi-mail-logo-dark){display:none!important}'
            .$darkButton;

        return ':root{color-scheme:'.$scheme.';supported-color-schemes:'.$scheme.'}'
            .'body{margin:0;padding:0}'
            .'table{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}'
            .'img{-ms-interpolation-mode:bicubic;max-width:100%}'
            .'.wi-mail-body img{max-width:100%!important;height:auto}'
            .'.wi-mail-body a{color:'.$brand['primary'].'}'
            .'.wi-mail-body p:last-child{margin-bottom:0}'
            .'@media screen and (max-width:620px){.wi-mail-body{padding:28px 22px 12px!important}.wi-mail-heading{font-size:20px!important}}'
            .($dark
                ? '@media (prefers-color-scheme:dark){'.$darkRules.'}'
                    .'[data-ogsc] .wi-mail-card{background-color:#18181b!important}'
                : '');
    }

    /**
     * URL pubblico di un logo di `$PATH` (`logo`, `logoWhite`), solo se il
     * file esiste sotto `ROOT`: niente immagini rotte nella mail.
     */
    private static function logo(string $property): ?string
    {
        $path = $GLOBALS['PATH'] ?? null;
        $url = is_object($path) ? (string) ($path->{$property} ?? '') : '';

        // Gmail e Outlook non mostrano gli SVG: meglio il nome.
        if ($url === '' || preg_match('#\.svgz?(?:[?\#]|$)#i', $url) === 1) {
            return null;
        }

        $root = (string) ($GLOBALS['ROOT'] ?? '');
        $marker = '/assets/upload/';
        $position = strpos($url, $marker);

        if ($root !== '' && $position !== false) {
            $file = $root.$marker.substr($url, $position + strlen($marker));

            if (!is_file($file)) {
                return null;
            }

            if (class_exists(\Wonder\App\Support\Asset::class)) {
                try {
                    return \Wonder\App\Support\Asset::version($url);
                } catch (\Throwable) {
                }
            }
        }

        return $url;
    }

    /** @return array<string, string> */
    private static function society(): array
    {
        $society = $GLOBALS['SOCIETY'] ?? null;
        $read = static fn (string $key): string => is_object($society) ? trim((string) ($society->{$key} ?? '')) : '';

        $legal = $read('legal_name');
        $name = $read('name');

        $fiscal = '';
        $pi = $read('pi');
        $cf = $read('cf');

        if ($pi !== '' && $pi === $cf) {
            $fiscal = 'P.IVA e C.F. '.$pi;
        } elseif ($pi !== '' || $cf !== '') {
            $fiscal = trim(($pi !== '' ? 'P.IVA '.$pi : '').($pi !== '' && $cf !== '' ? ' · ' : '').($cf !== '' ? 'C.F. '.$cf : ''));
        }

        $address = $read('legal_street') !== '' ? $read('addressLegal') : $read('address');

        // `address` viene composto anche quando le parti sono vuote: ", ()".
        if (trim($address, " ,()") === '') {
            $address = '';
        }

        $url = defined('APP_URL') ? (string) APP_URL : '';

        return [
            'name' => $name !== '' ? $name : ($legal !== '' ? $legal : 'Wonder Image'),
            'legal' => $legal !== '' ? $legal : $name,
            'address' => $address,
            'fiscal' => $fiscal,
            'email' => $read('email'),
            'tel' => $read('tel') !== '' ? $read('tel') : $read('cel'),
            'url' => preg_match('#^https?://#i', $url) === 1 ? $url : '',
        ];
    }

    private static function lang(): string
    {
        try {
            if (function_exists('__l')) {
                $lang = (string) __l();

                if (preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', $lang) === 1) {
                    return $lang;
                }
            }
        } catch (\Throwable) {
        }

        return 'it';
    }

    /** @return array{primary: string, contrast: string} */
    private static function readable(string $primary, string $contrast): array
    {
        if (self::luminance($primary) > 0.8) {
            return ['primary' => self::FALLBACK_PRIMARY, 'contrast' => '#ffffff'];
        }

        if (abs(self::luminance($primary) - self::luminance($contrast)) < 0.3) {
            $contrast = self::luminance($primary) > 0.5 ? '#18181b' : '#ffffff';
        }

        return ['primary' => $primary, 'contrast' => $contrast];
    }

    private static function hex(string $value): ?string
    {
        $value = strtolower(trim($value));

        if (preg_match('/^#([0-9a-f]{3})$/', $value, $match) === 1) {
            $value = '#'.$match[1][0].$match[1][0].$match[1][1].$match[1][1].$match[1][2].$match[1][2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2)
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
