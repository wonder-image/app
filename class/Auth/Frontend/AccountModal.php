<?php

namespace Wonder\Auth\Frontend;

use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\Text;

/** Modal del pannello: form POST con CSRF, Salva nero a tutta larghezza spento finché mancano i campi obbligatori. */
final class AccountModal
{
    public static function make(string $id, string $title, array $fields, string $action, array $hidden = [], array $errors = [], bool $open = false): Modal
    {
        $body = [];
        if ($errors !== []) {
            $body[] = Alert::make(implode("\n", $errors), 'error')->title((string) __t('account.error_title'));
        }
        $body[] = AccountAddressForm::layout($fields);
        $modal = Modal::make($title)->id($id)->frontend()->scrollable()
            ->form($action, 'post', $hidden)
            ->components($body)
            ->footer([self::submit((string) __t('account.actions.save'))]);
        return $open ? $modal->addClass('wi-show') : $modal;
    }

    public static function confirm(string $id, string $title, string $text, string $action, string $label): Modal
    {
        return Modal::make($title)->id($id)->frontend()
            ->form($action, 'post')
            ->components([Text::make($text)])
            ->footer([self::submit($label)]);
    }

    private static function submit(string $label): Button
    {
        return Button::make($label)->type('submit')->variant('black')->block()->addClass('wi-input-submit wi-submit');
    }
}
