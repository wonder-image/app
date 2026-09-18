<?php

namespace Wonder\Backend\Support;

use Throwable;

/**
 * Avviso in coda per la pagina successiva.
 *
 * Il backend notifica con i toast (`alertToast()` della lib), ma un
 * salvataggio finisce con un redirect: il messaggio deve sopravvivere al
 * cambio di pagina. Qui resta in sessione finché `alert()` in body-end lo
 * legge e lo trasforma nella chiamata JavaScript.
 *
 * Due forme:
 * - `code()` per i codici di `resources/lang/<lingua>/notifications.json`, gli
 *   stessi già usati da `?alert=650`;
 * - `custom()` (e la scorciatoia `saved()`) per i messaggi scritti al
 *   momento, che il JavaScript del frontend non sa mostrare: escono solo
 *   nel backend.
 *
 * Il testo finisce dentro l'avviso come HTML, esattamente come le
 * traduzioni (che contengono `<br>`): va scritto dal codice, non preso
 * così com'è da quello che ha digitato un utente.
 */
final class FlashAlert
{
    /** Chiave di sessione dove aspetta l'avviso. */
    public const KEY = 'wi_flash_alert';

    /** Livelli che `alertTheme()` sa colorare. */
    private const LEVELS = ['success', 'warning', 'error', 'danger', 'info'];

    /** Mette in coda un codice delle notifiche (es. 650 "Modificato"). */
    public static function code(int|string $code): void
    {
        $_SESSION[self::KEY] = ['code' => (string) $code];
    }

    /** Mette in coda un avviso scritto a mano: solo backend. */
    public static function custom(string $title, string $text, string $type = 'success'): void
    {
        $level = strtolower(trim($type));

        $_SESSION[self::KEY] = [
            'code' => 'custom',
            'type' => in_array($level, self::LEVELS, true) ? $level : 'success',
            'title' => $title,
            'text' => $text,
        ];
    }

    /** Avviso di salvataggio riuscito: il titolo è quello del codice 650. */
    public static function saved(string $text): void
    {
        self::custom(self::title('650', 'Modificato'), $text);
    }

    /** Legge e svuota la coda. @return array<string, string> */
    public static function pull(): array
    {
        $alert = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        return is_array($alert) ? $alert : [];
    }

    /**
     * Chiamata JavaScript da stampare in pagina, stringa vuota se non c'è
     * niente in coda. `$custom` è falso dove il JavaScript accetta solo il
     * codice, cioè nel frontend.
     */
    public static function script(bool $custom = true): string
    {
        $alert = self::pull();
        $code = (string) ($alert['code'] ?? '');

        if ($code === 'custom') {
            if (!$custom) {
                return '';
            }

            return 'alertToast("custom", '
                .self::js((string) ($alert['type'] ?? 'success')).', '
                .self::js((string) ($alert['title'] ?? '')).', '
                .self::js((string) ($alert['text'] ?? '')).');';
        }

        // Come in `alert()`: nello script solo codici numerici, mai testo
        // che possa diventare codice.
        return is_numeric($code) ? 'alertToast('.(int) $code.');' : '';
    }

    /** Titolo tradotto di un codice, con ripiego se le lingue non ci sono. */
    private static function title(string $code, string $fallback): string
    {
        try {
            $title = (string) __t("notifications.{$code}.title");
        } catch (Throwable) {
            return $fallback;
        }

        return trim($title) === '' ? $fallback : $title;
    }

    /** Stringa JavaScript sicura: niente virgolette o tag che scappano. */
    private static function js(string $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        );

        return $encoded === false ? '""' : $encoded;
    }
}
