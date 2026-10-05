<?php

namespace Wonder\App\Resources\Contacts;

use InvalidArgumentException;
use Wonder\App\Models\Contacts\{Contact, ContactAddress};
use Wonder\App\ResourceSchema\{FormField, NavigationSchema, PageSchema, TableColumn, TableLayoutSchema};
use Wonder\Auth\Frontend\{AccountAddressForm, AccountAddressValidation, AuthSession};

/** Shipping addresses are managed independently, always bound to an existing contact. */
class ContactAddressResource extends ContactResource
{
    public static string $model = ContactAddress::class;
    public static function path(): string { return 'contact-addresses'; }
    public static function textSchema(): array
    {
        return ['label' => (string) __t('contacts.address'), 'plural_label' => (string) __t('contacts.addresses')];
    }
    public static function labelSchema(): array
    {
        return ContactAddress::address()->labels() + ['contact_id' => (string) __t('contacts.contact'), 'label' => (string) __t('contacts.address_label')];
    }
    public static function contactOptions(): array
    {
        $options = [];
        foreach (Contact::all() as $row) { $options[(int) $row['id']] = parent::displayName($row); }
        return $options;
    }
    public static function formSchema(): array
    {
        return [
            FormField::key('contact_id')->selectSearch(static::contactOptions())->required(),
            FormField::key('label')->text(),
            ...array_values(AccountAddressForm::fields(ContactAddress::address())),
            FormField::key('_contact_csrf')->hidden()->value(AuthSession::csrfToken()),
        ];
    }
    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        if ($mode === 'create' && !isset($values['contact_id']) && isset($_GET['contact_id']) && ctype_digit((string) $_GET['contact_id'])) {
            $values['contact_id'] = (int) $_GET['contact_id'];
        }
        return parent::mutateFormValues($values, $mode, $context);
    }
    public static function navigationSchema(): NavigationSchema { return NavigationSchema::for(static::class)->enabled(false); }
    public static function pageSchema(): PageSchema { return PageSchema::for(static::class)->disable(['delete']); }
    public static function tableSchema(): array
    {
        return [TableColumn::key('contact_id')->text(), TableColumn::key('label')->text()->link('edit'),
            TableColumn::key('name')->text()->link('edit'), TableColumn::key('street')->text(), TableColumn::key('city')->text(),
            TableColumn::key('actions')->button()->actions(['edit'])];
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->searchFields(['label', 'name', 'surname', 'city', 'street'])
            ->filterCustom((string) __t('contacts.contact'), 'contact_id', static::contactOptions());
    }
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        static::verifyFormToken($values);
        $values = array_intersect_key($values, static::labelSchema());
        $contactId = filter_var($values['contact_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$contactId || !Contact::findById($contactId) || ($oldValues !== null && $contactId !== (int) $oldValues['contact_id'])) {
            throw new InvalidArgumentException((string) __t('contacts.invalid_contact'));
        }
        if (isset($values['label']) && (!is_string($values['label']) || mb_strlen($values['label']) > 100)) {
            throw new InvalidArgumentException((string) __t('account.validation.invalid', ['field' => static::labelSchema()['label']]));
        }
        $errors = AccountAddressValidation::validate(ContactAddress::address(), $values);
        if ($errors !== []) { throw new InvalidArgumentException(implode("\n", $errors)); }
        $values['contact_id'] = $contactId;
        return $values;
    }
}
