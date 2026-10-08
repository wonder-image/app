<?php

namespace Wonder\Auth\Frontend;

use Throwable;
use Wonder\App\Models\Contacts\ContactAddress;

/** Indirizzi della scheda del cliente: ognuno si legge, si cambia e si elimina solo dalla sua scheda. */
final class AccountAddresses
{
    public static function all(int $contactId): array
    {
        if ($contactId <= 0) { return []; }
        try {
            $rows = ContactAddress::find(['contact_id' => $contactId], null, 'position', 'ASC');
            return is_array($rows) ? array_values($rows) : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function find(int $contactId, int $addressId): ?array
    {
        if ($contactId <= 0 || $addressId <= 0) { return null; }
        $row = ContactAddress::find(['id' => $addressId, 'contact_id' => $contactId], 1);
        return is_array($row) && $row !== [] ? $row : null;
    }

    /** @return object{success: bool, messages: list<string>, id: int} */
    public static function save(int $contactId, array $input, ?int $addressId = null): object
    {
        if ($addressId !== null && self::find($contactId, $addressId) === null) {
            return (object) ['success' => false, 'messages' => [(string) __t('account.errors.save')], 'id' => 0];
        }
        $address = ContactAddress::address();
        $values = array_intersect_key($input, $address->labels());
        $messages = AccountAddressValidation::validate($address, $values);
        if ($messages !== []) {
            return (object) ['success' => false, 'messages' => $messages, 'id' => $addressId ?? 0];
        }
        if ($addressId === null) {
            $result = ContactAddress::create($values + ['contact_id' => $contactId, 'position' => count(self::all($contactId)) + 1]);
            $id = (int) ($result->insert_id ?? 0);
        } else {
            $result = ContactAddress::update($values, $addressId);
            $id = $addressId;
        }
        return ($result->success ?? false)
            ? (object) ['success' => true, 'messages' => [], 'id' => $id]
            : (object) ['success' => false, 'messages' => [(string) __t('account.errors.save')], 'id' => $id];
    }

    public static function delete(int $contactId, int $addressId): bool
    {
        return self::find($contactId, $addressId) !== null && (bool) (ContactAddress::delete($addressId)->success ?? false);
    }

    /** Righe della scheda: «via numero, cap» e «città (provincia)». */
    public static function card(array $address): array
    {
        $phone = trim((string) ($address['phone_prefix'] ?? '').' '.(string) ($address['phone'] ?? ''));
        $province = trim((string) ($address['province'] ?? ''));
        return [
            'name' => trim((string) ($address['name'] ?? '').' '.(string) ($address['surname'] ?? '')),
            'phone' => $phone,
            'phone_href' => $phone !== '' ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : '',
            'lines' => array_values(array_filter([
                trim(trim((string) ($address['street'] ?? '').' '.(string) ($address['number'] ?? '')).', '.(string) ($address['cap'] ?? ''), ', '),
                trim((string) ($address['city'] ?? '').($province !== '' ? ' ('.$province.')' : '')),
            ])),
        ];
    }
}
