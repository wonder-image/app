<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\Contacts\Contact;

/** Links an account to its fiscal identity without requiring billing data. */
class ContactAccount
{
    public static function link(int $userId, array $input = []): object
    {
        $user = \infoUser($userId, 'id');
        if (!($user->exists ?? false)) {
            return (object) ['success' => false, 'reason' => 'user_not_found'];
        }

        $byUser = Contact::find(['user_id' => $userId], 1);
        $byEmail = Contact::find(['email' => (string) ($user->email ?? '')], 1);
        $contact = is_array($byUser) && !empty($byUser['id']) ? $byUser : $byEmail;

        if (is_array($contact) && !empty($contact['id'])
            && (int) ($contact['user_id'] ?? 0) > 0
            && (int) $contact['user_id'] !== $userId) {
            return (object) ['success' => false, 'reason' => 'contact_link_conflict'];
        }

        $values = [
            'name' => (string) ($user->name ?? ''),
            'surname' => (string) ($user->surname ?? ''),
            'email' => (string) ($user->email ?? ''),
            'user_id' => $userId,
            'is_customer' => 'true',
            'active' => 'true',
        ];

        if (!empty($input['phone'])) {
            $values['phone_prefix'] = preg_replace('/[^0-9+]/', '', (string) ($input['phone_prefix'] ?? ''));
            $values['phone'] = preg_replace('/\D+/', '', (string) $input['phone']);
        }

        $result = is_array($contact) && !empty($contact['id'])
            ? Contact::update($values, (int) $contact['id'])
            : Contact::create($values);

        return (object) [
            'success' => (bool) ($result->success ?? false),
            'reason' => ($result->success ?? false) ? '' : 'contact_write_failed',
            'contact_id' => (int) ($contact['id'] ?? $result->insert_id ?? 0),
        ];
    }
}
