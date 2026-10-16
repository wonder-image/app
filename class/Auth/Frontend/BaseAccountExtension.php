<?php

namespace Wonder\Auth\Frontend;

/** Estensione che non fa nulla: i moduli sovrascrivono solo gli hook che servono. */
abstract class BaseAccountExtension implements AccountExtension, AccountPageExtension
{
    public function routes(): void {}
    public function navigation(array $items, object $user): array { return $items; }
    public function overviewRows(array $rows, object $user): array { return $rows; }
    public function personalRows(array $rows, object $user): array { return $rows; }
    public function personalFields(array $fields, object $user): array { return $fields; }
    public function validatePersonal(array $input, object $user): array { return []; }
    public function personalUserValues(array $input, object $user): array { return []; }
    public function afterPersonalSaved(array $input, object $user): void {}
    public function head(): string { return ''; }
    public function personalFormFields(array $fields, object $user, array $values): array { return $fields; }
    public function personalModals(array $modals, object $user, string $open, array $errors, array $values): array { return $modals; }
    public function savePersonalForm(string $form, object $user, array $input): ?array { return null; }
    public function pageErrors(array $errors, object $user, string $page): array { return $errors; }
    public function afterEmailConfirmed(int $userId, string $email): void {}
}
