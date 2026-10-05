<?php

namespace Wonder\Auth\Frontend;

use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\Button;
use Wonder\View\Component;

/** Composes the existing modal and form renderers, without owning persistence. */
final class AccountAddressModal
{
    public static function make(string $id, string $title, array $fields, string $action, string $cancelUrl, string $csrf): Modal
    {
        $formId = $id === 'shipping-new' ? 'save_shipping_address' : 'save_shipping_address_'.$id;
        return Modal::make($title)->id($id)->frontend()->scrollable()->components([
            Component::make('frontend.account.address-form', [
                'fields' => $fields, 'action' => $action, 'cancel_url' => $cancelUrl,
                'csrf_token' => $csrf, 'form_id' => $formId, 'show_actions' => false,
            ]),
        ])->footer([
            Button::make((string) \__t('account.actions.cancel'))->type('button')->outline()->addClass('wi-close-modal'),
            Button::make((string) \__t('account.actions.save'))->type('submit')->attr('form', $formId),
        ]);
    }
}
