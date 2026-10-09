<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Input;
use Wonder\Auth\RememberMe;

/**
 * Cambio password dal pannello account, indipendente da rotte e moduli.
 * Un account senza password locale (creato con Google o Apple) la imposta
 * senza indicare quella attuale.
 */
class AccountPassword
{
    /** @return array<string, Input> */
    public static function fields(bool $hasPassword): array
    {
        $fields = [];

        if ($hasPassword) {
            $fields['current_password'] = FormField::key('current_password')->password()
                ->label((string) \__t('account.password.current'))
                ->autocomplete('current-password')
                ->required();
        }

        $fields['password'] = FormField::key('password')->password()
            ->label((string) \__t('account.password.new'))
            ->autocomplete('new-password')
            ->required();

        return $fields;
    }

    /** @return array<string, string> */
    public static function validate(array $input, string $currentHash): array
    {
        $errors = [];
        $current = (string) ($input['current_password'] ?? '');

        if (trim($currentHash) !== '') {
            if (trim($current) === '') {
                $errors['current_password'] = 'required';
            } elseif (!\checkPassword($current, $currentHash)) {
                $errors['current_password'] = 'wrong';
            }
        }

        $errors += AuthValidator::completion($input, true, false, false);

        if (!isset($errors['password']) && trim($currentHash) !== '' && \checkPassword((string) ($input['password'] ?? ''), $currentHash)) {
            $errors['password'] = 'same';
        }

        return $errors;
    }

    /** @return object{success: bool, errors: array<string, string>} */
    public static function change(int $userId, array $input): object
    {
        $user = $userId > 0 ? \sqlSelect('user', ['id' => $userId], 1) : null;
        if (!($user->exists ?? false)) {
            return (object) ['success' => false, 'errors' => ['user' => 'missing']];
        }

        $errors = self::validate($input, (string) ($user->row['password'] ?? ''));
        if ($errors !== []) {
            return (object) ['success' => false, 'errors' => $errors];
        }

        $saved = \sqlModify('user', ['password' => \hashPassword((string) $input['password'])], 'id', $userId);
        if (!($saved->success ?? false)) {
            return (object) ['success' => false, 'errors' => ['user' => 'save']];
        }

        // Una sessione o un cookie "ricordami" rubati prima del cambio non restano validi.
        RememberMe::revokeUser($userId);
        // Con la password rubata si può aver chiesto un cambio email: quel link non vale più.
        AccountEmail::revokeOpen($userId);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        return (object) ['success' => true, 'errors' => []];
    }
}
