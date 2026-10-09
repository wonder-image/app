<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\Contacts\Contact;

/** Dati di fatturazione della scheda del cliente. */
final class AccountBilling
{
    /** @return object{success: bool, messages: list<string>} */
    public static function save(int $contactId, array $input): object
    {
        if ($contactId <= 0) {
            return (object) ['success' => false, 'messages' => [(string) __t('account.errors.contact')]];
        }
        $billing = Contact::billing();
        $values = array_intersect_key($input, $billing->labels());
        $messages = AccountAddressValidation::validate($billing, $values);
        if ($messages !== []) {
            return (object) ['success' => false, 'messages' => $messages];
        }
        $result = Contact::update($values, $contactId);

        return ($result->success ?? false)
            ? (object) ['success' => true, 'messages' => []]
            : (object) ['success' => false, 'messages' => [(string) __t('account.errors.save')]];
    }

    /** Una sola riga: intestatario, codice fiscale e partita IVA, indirizzo. */
    public static function rows(array $contact): array
    {
        $text = static fn (string $key): string => trim((string) ($contact[$key] ?? ''));
        $business = $text('business_name');
        $person = trim($text('name').' '.$text('surname'));
        $holder = ($contact['type'] ?? '') === 'business' ? ($business ?: $person) : ($person ?: $business);
        $tax = implode(' / ', array_filter([$text('cf'), $text('pi')]));
        $address = implode(', ', array_filter([
            trim($text('street').' '.$text('number')),
            trim($text('cap').' '.$text('city').' '.$text('province')),
            $text('country'),
        ]));
        $value = static fn (string $text): string => $text !== '' ? $text : '—';

        return [
            ['key' => 'billing', 'columns' => [
                ['label' => (string) __t('account.billing.holder'), 'value' => $value($holder)],
                ['label' => (string) __t('account.billing.tax'), 'value' => $value($tax)],
                ['label' => (string) __t('account.billing.address'), 'value' => $value($address)],
            ], 'action' => [
                'label' => (string) __t('account.actions.edit'), 'href' => '', 'modal' => 'account-billing',
                'icon' => 'bi bi-pencil', 'disabled' => false, 'hint' => '',
            ]],
        ];
    }
}
