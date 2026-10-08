<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\Contacts\Contact;
use Wonder\Sql\Transaction;

/** Dati personali dal pannello: utente e scheda insieme, o niente. */
final class AccountPersonal
{
    /**
     * @return object{success: bool, errors: array<string, string>, messages: list<string>}
     */
    public static function save(int $userId, array $input, AccountPanel $panel, bool $phoneRequired): object
    {
        $user = \infoUser($userId, 'id');
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $surname = trim((string) ($input['surname'] ?? ''));
        if ($name === '') { $errors['name'] = 'required'; }
        if ($surname === '') { $errors['surname'] = 'required'; }
        $errors += array_intersect_key(AuthValidator::completion($input, false, $phoneRequired, false), ['phone' => true]);
        $phone = AuthValidator::canonicalPhone($input);
        if (!isset($errors['phone']) && $phone !== '' && !\unique($phone, 'user', 'phone', $userId)) {
            $errors['phone'] = 'not_unique';
        }
        $birth = trim((string) ($input['birth_date'] ?? ''));
        $date = $birth === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
        if ($birth !== '' && (!$date || $date->format('Y-m-d') !== $birth || $date > new \DateTimeImmutable('today'))) {
            $errors['birth_date'] = 'invalid';
        }
        $messages = $panel->validatePersonal($input, $user);
        if ($errors !== [] || $messages !== []) {
            return (object) ['success' => false, 'errors' => $errors, 'messages' => $messages];
        }

        try {
            Transaction::run(static function () use ($userId, $input, $panel, $user, $name, $surname, $phone, $birth): void {
                $GLOBALS['ALERT'] = null;
                $result = \user(array_merge($panel->personalUserValues($input, $user), [
                    'name' => $name, 'surname' => $surname,
                    'phone_prefix' => (string) ($input['phone_prefix'] ?? ''), 'phone' => $phone,
                    'area' => 'frontend', 'authority' => 'client',
                ]), $userId);
                if (!empty($GLOBALS['ALERT']) || !($result->user->exists ?? false)) {
                    throw new AccountSaveFailed('user');
                }
                $link = ContactAccount::link($userId, ['phone_prefix' => (string) ($input['phone_prefix'] ?? ''), 'phone' => (string) ($input['phone'] ?? '')]);
                if (!($link->success ?? false)) {
                    throw new AccountSaveFailed('contact');
                }
                Contact::update(['birth_date' => $birth !== '' ? $birth : null], (int) $link->contact_id);
                $panel->afterPersonalSaved($input, $result->user);
            });
        } catch (AccountSaveFailed $e) {
            return (object) ['success' => false, 'errors' => $e->getMessage() === 'contact' ? ['contact' => 'conflict'] : ['user' => 'save'], 'messages' => []];
        }

        return (object) ['success' => true, 'errors' => [], 'messages' => []];
    }

    public static function rows(object $user, array $contact, bool $hasPassword): array
    {
        $edit = static fn (string $modal): array => ['label' => (string) __t('account.actions.edit'), 'href' => '', 'modal' => $modal, 'icon' => 'bi bi-pencil', 'disabled' => false, 'hint' => ''];
        $birth = (string) ($contact['birth_date'] ?? '');
        return [
            ['key' => 'personal', 'columns' => [
                ['label' => (string) __t('account.personal.name'), 'value' => trim((string) ($user->name ?? '').' '.(string) ($user->surname ?? ''))],
                ['label' => (string) __t('account.personal.birth_date'), 'value' => $birth !== '' ? date('d/m/Y', strtotime($birth)) : '—'],
                ['label' => (string) __t('account.personal.phone'), 'value' => trim((string) ($user->phone ?? '')) ?: '—'],
            ], 'action' => $edit('account-personal')],
            ['key' => 'email', 'columns' => [['label' => (string) __t('account.email.label'), 'value' => (string) ($user->email ?? '')]], 'action' => $edit('account-email')],
            ['key' => 'password', 'columns' => [['label' => (string) __t('account.password.label'), 'value' => $hasPassword ? '**********' : (string) __t('account.password.summary_missing')]], 'action' => $edit('account-password')],
        ];
    }
}
