<?php

namespace Wonder\App\Resources\Contacts;

use InvalidArgumentException;
use Wonder\App\Models\Contacts\Contact;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\App\ResourceSchema\{ApiSchema, FormField, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};
use Wonder\Auth\Frontend\AuthSession;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

/** App-only address book, without commercial fields or account credentials. */
class ContactResource extends Resource
{
    public static string $model = Contact::class;
    public static string $orderColumn = 'id';

    public static function path(): string { return 'contacts'; }
    public static function isTableFallback(): bool { return true; }
    public static function textSchema(): array
    {
        return ['label' => (string) __t('contacts.contact'), 'plural_label' => (string) __t('contacts.title')];
    }
    public static function labelSchema(): array
    {
        return Contact::billing()->labels() + [
            'email' => (string) __t('contacts.email'), 'note' => (string) __t('contacts.note'),
            'active' => (string) __t('contacts.active'),
        ];
    }
    public static function formSchema(): array
    {
        $fields = Contact::billing()->formSchema();
        foreach (['business_name', 'pi', 'sdi', 'pec'] as $key) { $fields[$key]->visibleWhen('type', 'business'); }
        $fields['business_name']->required();
        foreach (['name', 'surname'] as $key) { $fields[$key]->required()->visibleWhen('type', 'private'); }
        return [
            ...array_values($fields),
            FormField::key('email')->email(),
            FormField::key('note')->textarea(),
            FormField::key('active')->select(['true' => (string) __t('contacts.enabled'), 'false' => (string) __t('contacts.disabled')])->value('true')->required(),
            FormField::key('_contact_csrf')->hidden()->value(AuthSession::csrfToken()),
        ];
    }
    public static function formLayoutSchema(): ?Form
    {
        $widths = ['type' => 12, 'country' => 6, 'province' => 6, 'city' => 9, 'cap' => 3, 'street' => 9,
            'number' => 3, 'phone_prefix' => 3, 'phone' => 9, 'name' => 6, 'surname' => 6,
            'pi' => 6, 'sdi' => 6];
        $fields = array_map(static fn ($field) => $field->columnSpan($widths[$field->name] ?? 12), static::formSchema());
        return (new Form)->columns(12)->components([(new Card)->columns(12)->columnSpan(12)->components($fields)]);
    }
    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit')->formatter(static fn (array $row): string => htmlspecialchars(static::displayName($row), ENT_QUOTES, 'UTF-8')),
            TableColumn::key('email')->text(), TableColumn::key('city')->text(),
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }
    public static function displayName(array $row): string
    {
        $name = ($row['type'] ?? 'private') === 'business' ? (string) ($row['business_name'] ?? '') : ($row['name'] ?? '').' '.($row['surname'] ?? '');
        return trim($name) ?: '#'.($row['id'] ?? '');
    }
    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && trim((string) ($values['phone_prefix'] ?? '')) === '') {
            $country = (string) ($values['country'] ?? 'IT');
            $prefix = $country !== '' ? (string) countryPhonePrefix($country) : '';
            if ($prefix !== '' && !str_starts_with($prefix, '+')) { $prefix = '+'.ltrim($prefix, '0'); }
            $values['phone_prefix'] = $prefix;
        }
        return $values;
    }
    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->disable(['delete'])->redirect('store', 'edit')->redirect('update', 'edit')
            ->actions('edit', static fn (array $row): array => [[
                'label' => (string) __t('contacts.addresses'), 'icon' => 'bi bi-geo-alt',
                'href' => __r('backend.resource.contact-addresses.list').'?contact_id='.(int) ($row['id'] ?? 0),
            ]]);
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->backendCrud(['admin', 'administrator']);
    }
    public static function navigationSchema(): NavigationSchema
    {
        // A richer module/site contact Resource keeps the menu; the generic URLs remain available.
        $visible = true;
        foreach (ResourceRegistry::classes() as $resource) {
            if ($resource !== static::class && !is_subclass_of($resource, self::class) && $resource::modelTable() === Contact::$table) {
                $visible = false;
                break;
            }
        }
        return NavigationSchema::for(static::class)->title((string) __t('contacts.title'))->sectionOrder(400)
            ->authority(['admin', 'administrator'])->enabled($visible);
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->searchFields(['name', 'surname', 'business_name', 'email', 'city']);
    }
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        static::verifyFormToken($values);
        $values = array_intersect_key($values, static::labelSchema());
        foreach ($values as $key => $value) {
            if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException((string) __t('account.validation.invalid', ['field' => static::labelSchema()[$key]])); }
        }
        if (!in_array($values['type'] ?? '', ['private', 'business'], true)
            || !in_array($values['active'] ?? '', ['true', 'false'], true)) {
            throw new InvalidArgumentException((string) __t('contacts.invalid'));
        }
        $required = $values['type'] === 'business' ? ['business_name'] : ['name', 'surname'];
        foreach ($required as $key) {
            if (trim((string) ($values[$key] ?? '')) === '') {
                throw new InvalidArgumentException((string) __t('account.validation.required', ['field' => static::labelSchema()[$key]]));
            }
        }
        $country = (string) ($values['country'] ?? '');
        $province = (string) ($values['province'] ?? '');
        if (($country !== '' && !array_key_exists($country, countries()))
            || ($province !== '' && !array_key_exists($province, states($country)))) {
            throw new InvalidArgumentException((string) __t('contacts.invalid_address'));
        }
        if ($action === 'store') { $values['code'] = Contact::newCode(); }
        return $values;
    }
    protected static function verifyFormToken(array $values): void
    {
        if (!is_string($values['_contact_csrf'] ?? null) || !AuthSession::verify($values['_contact_csrf'])) {
            throw new InvalidArgumentException((string) __t('contacts.csrf'));
        }
    }
}
