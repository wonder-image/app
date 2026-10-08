<?php

namespace Wonder\Frontend\Support;

use InvalidArgumentException;
use Wonder\Elements\Components\Alert;

/**
 * Messaggio frontend che sopravvive a un redirect e viene mostrato una volta.
 *
 * Il testo e il titolo restano dati: la presentazione è affidata al componente
 * Alert quando il layout frontend consuma il messaggio.
 */
final class FlashMessage
{
    public const KEY = 'wi_frontend_flash_message';

    private const LEVELS = ['info', 'success', 'warning', 'error'];

    public static function info(string $message, string $title = ''): void
    {
        self::put($message, 'info', $title);
    }

    public static function success(string $message, string $title = ''): void
    {
        self::put($message, 'success', $title);
    }

    public static function warning(string $message, string $title = ''): void
    {
        self::put($message, 'warning', $title);
    }

    public static function error(string $message, string $title = ''): void
    {
        self::put($message, 'error', $title);
    }

    public static function put(string $message, string $level = 'info', string $title = ''): void
    {
        $level = strtolower(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(
                "Livello {$level} non valido. Valori ammessi: ".implode(', ', self::LEVELS)
            );
        }

        $message = trim($message);
        if ($message === '') {
            return;
        }

        $_SESSION[self::KEY] = [
            'level' => $level,
            'title' => trim($title),
            'message' => $message,
        ];
    }

    /** @return array{level: string, title: string, message: string}|array{} */
    public static function pull(): array
    {
        $message = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        if (!is_array($message)) {
            return [];
        }

        $level = strtolower(trim((string) ($message['level'] ?? '')));
        $text = trim((string) ($message['message'] ?? ''));
        if (!in_array($level, self::LEVELS, true) || $text === '') {
            return [];
        }

        return [
            'level' => $level,
            'title' => trim((string) ($message['title'] ?? '')),
            'message' => $text,
        ];
    }

    public static function render(): string
    {
        $message = self::pull();
        if ($message === []) {
            return '';
        }

        $alert = Alert::make($message['message'], $message['level']);
        if ($message['title'] !== '') {
            $alert->title($message['title']);
        }

        return $alert->render();
    }
}
