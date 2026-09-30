<?php
/** php tests/App/PageSchema/AccountPageSchemaTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\PageSchema\AccountPageSchema;

$ignored = static fn (array $schema): array => array_keys(array_filter(
    $schema,
    static fn ($field): bool => str_contains($field->render('bootstrap'), ' data-wi-save-bar-ignore')
));

check('Solo la password di conferma del profilo e ignorata dalla barra', fn () =>
    $ignored(AccountPageSchema::profileFormSchema([])) === ['password']
);

check('Form password, login, ripristino e nuova password non hanno campi ignorati', fn () =>
    $ignored(AccountPageSchema::passwordFormSchema()) === []
    && $ignored(AccountPageSchema::loginFormSchema()) === []
    && $ignored(AccountPageSchema::restoreFormSchema()) === []
    && $ignored(AccountPageSchema::setPasswordFormSchema()) === []
);

summary();
