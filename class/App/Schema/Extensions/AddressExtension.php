<?php

namespace Wonder\App\Schema\Extensions;

use InvalidArgumentException;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;
use Wonder\Support\Prettify\Address as PrettifyAddress;

final class AddressExtension
{
    private string $prefix;
    private string $mode;
    private string $linkKey;
    private ?string $countryDefault;
    /** @var list<string> */
    private array $allowedCountries = [];
    /** @var list<string> */
    private array $requiredFields = [];

    private bool $withMore = true;
    private bool $withLink = true;
    private bool $withPhone = false;
    private bool $withContactName = false;
    private bool $withType = false;
    private bool $withCompanyData = false;

    private function __construct(
        ?string $prefix = null,
        string $mode = 'simple',
        string $linkKey = 'link',
        ?string $countryDefault = null,
    ) {
        $mode = strtolower(trim($mode));

        if (!in_array($mode, ['simple', 'billing'], true)) {
            throw new InvalidArgumentException("AddressExtension mode non valido: {$mode}.");
        }

        $this->prefix = $this->normalizePrefix($prefix);
        $this->mode = $mode;
        $this->linkKey = $this->normalizeFieldName($linkKey, 'link');
        $this->countryDefault = $this->normalizeCountry($countryDefault);

        if ($mode === 'billing') {
            $this->withPhone = true;
            $this->withContactName = true;
            $this->withType = true;
            $this->withCompanyData = true;
        }
    }

    public static function make(
        ?string $prefix = null,
        string $mode = 'simple',
        string $linkKey = 'link',
        ?string $countryDefault = null,
    ): self {
        return new self($prefix, $mode, $linkKey, $countryDefault);
    }

    public static function simple(
        ?string $prefix = null,
        string $linkKey = 'link',
        ?string $countryDefault = null,
    ): self {
        return new self($prefix, 'simple', $linkKey, $countryDefault);
    }

    public static function billing(
        ?string $prefix = null,
        string $linkKey = 'link',
        ?string $countryDefault = null,
    ): self {
        return new self($prefix, 'billing', $linkKey, $countryDefault);
    }

    public function withMore(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->withMore = $enabled;

        return $clone;
    }

    public function withLink(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->withLink = $enabled;

        return $clone;
    }

    public function withPhone(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->withPhone = $enabled;

        return $clone;
    }

    public function withContactName(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->withContactName = $enabled;

        return $clone;
    }

    public function withType(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->withType = $enabled;

        return $clone;
    }

    public function allowedCountries(array $countries): self
    {
        $clone = clone $this;
        $clone->allowedCountries = $this->normalizeCountries($countries);

        if ($clone->countryDefault !== null && !$clone->isAllowedCountry($clone->countryDefault)) {
            $clone->countryDefault = $clone->allowedCountries[0] ?? null;
        }

        return $clone;
    }

    public function requiredFields(array|string $fields): self
    {
        $clone = clone $this;
        $clone->requiredFields = $this->normalizeFieldList($fields);

        return $clone;
    }

    public function labels(): array
    {
        $labels = [
            $this->key('country') => __t('components.forms.fields.country.label'),
            $this->key('province') => __t('components.forms.fields.province.label'),
            $this->key('city') => __t('components.forms.fields.city.label'),
            $this->key('cap') => __t('components.forms.fields.cap.label'),
            $this->key('street') => __t('components.forms.fields.street.label'),
            $this->key('number') => __t('components.forms.fields.number.label'),
        ];

        if ($this->withMore) {
            $labels[$this->key('more')] = __t('components.forms.fields.more.label');
        }

        if ($this->withLink) {
            $labels[$this->key($this->linkKey)] = $this->linkLabel();
        }

        if ($this->withPhone) {
            $labels[$this->key('phone_prefix')] = __t('components.forms.fields.phone_prefix.label');
            $labels[$this->key('phone')] = __t('components.forms.fields.phone.label');
        }

        if ($this->withContactName) {
            $labels[$this->key('name')] = __t('components.forms.fields.name.label');
            $labels[$this->key('surname')] = __t('components.forms.fields.surname.label');
        }

        if ($this->withType) {
            $labels[$this->key('type')] = __t('components.forms.fields.type.label');
        }

        if ($this->withCompanyData) {
            $labels[$this->key('business_name')] = __t('components.forms.fields.business_name.label');
            $labels[$this->key('cf')] = __t('components.forms.fields.cf.label');
            $labels[$this->key('pi')] = __t('components.forms.fields.pi.label');
            $labels[$this->key('sdi')] = __t('components.forms.fields.sdi.label');
            $labels[$this->key('pec')] = __t('components.forms.fields.pec.label');
        }

        return $labels;
    }

    public function dataSchema(): array
    {
        $fields = [
            $this->dataField(Field::key($this->key('country'))->text(), 'country'),
            $this->dataField(Field::key($this->key('province'))->text(), 'province'),
            $this->dataField(Field::key($this->key('city'))->text(), 'city'),
            $this->dataField(Field::key($this->key('cap'))->text(), 'cap'),
            $this->dataField(Field::key($this->key('street'))->text(), 'street'),
            $this->dataField(Field::key($this->key('number'))->text(), 'number'),
        ];

        if ($this->withMore) {
            $fields[] = $this->dataField(Field::key($this->key('more'))->text(), 'more');
        }

        if ($this->withLink) {
            $fields[] = $this->dataField(Field::key($this->key($this->linkKey))->text(), 'link', $this->linkKey);
        }

        if ($this->withPhone) {
            $fields[] = $this->dataField(Field::key($this->key('phone_prefix'))->text(), 'phone_prefix');
            $fields[] = $this->dataField(Field::key($this->key('phone'))->text(), 'phone');
        }

        if ($this->withContactName) {
            $fields[] = $this->dataField(Field::key($this->key('name'))->text(), 'name');
            $fields[] = $this->dataField(Field::key($this->key('surname'))->text(), 'surname');
        }

        if ($this->withType) {
            $fields[] = $this->dataField(Field::key($this->key('type'))->text()->lower(), 'type');
        }

        if ($this->withCompanyData) {
            $fields[] = $this->dataField(Field::key($this->key('business_name'))->text(), 'business_name');
            $fields[] = $this->dataField(
                Field::key($this->key('cf'))->tin()
                    ->countryField($this->key('country'))
                    ->type('all'),
                'cf'
            );
            $fields[] = $this->dataField(
                Field::key($this->key('pi'))->vat()
                    ->countryField($this->key('country')),
                'pi'
            );
            $fields[] = $this->dataField(Field::key($this->key('sdi'))->text(), 'sdi');
            $fields[] = $this->dataField(Field::key($this->key('pec'))->email(), 'pec');
        }

        return $fields;
    }

    public function tableSchema(): array
    {
        $columns = [
            Column::key($this->key('country')),
            Column::key($this->key('province')),
            Column::key($this->key('city')),
            Column::key($this->key('cap')),
            Column::key($this->key('street')),
            Column::key($this->key('number')),
        ];

        if ($this->withMore) {
            $columns[] = Column::key($this->key('more'));
        }

        if ($this->withLink) {
            $columns[] = Column::key($this->key($this->linkKey));
        }

        if ($this->withPhone) {
            $columns[] = Column::key($this->key('phone_prefix'));
            $columns[] = Column::key($this->key('phone'));
        }

        if ($this->withContactName) {
            $columns[] = Column::key($this->key('name'));
            $columns[] = Column::key($this->key('surname'));
        }

        if ($this->withType) {
            $columns[] = Column::key($this->key('type'))->enum(['private', 'business']);
        }

        if ($this->withCompanyData) {
            $columns[] = Column::key($this->key('business_name'));
            $columns[] = Column::key($this->key('cf'));
            $columns[] = Column::key($this->key('pi'));
            $columns[] = Column::key($this->key('sdi'));
            $columns[] = Column::key($this->key('pec'));
        }

        return $columns;
    }

    public function formSchema(?string $country = null): array
    {
        $country = $this->resolveCountry($country);
        $schema = [];

        if ($this->withType) {
            $schema[$this->key('type')] = $this->formField(
                FormField::key($this->key('type'))
                ->select([
                    'private' => __t('components.forms.options.address_type.private'),
                    'business' => __t('components.forms.options.address_type.business'),
                ])
                ->value('private'),
                'type'
            );
        }

        if ($this->withContactName) {
            $schema[$this->key('name')] = $this->formField(FormField::key($this->key('name'))->text(), 'name');
            $schema[$this->key('surname')] = $this->formField(FormField::key($this->key('surname'))->text(), 'surname');
        }

        if ($this->withCompanyData) {
            $schema[$this->key('business_name')] = $this->formField(FormField::key($this->key('business_name'))->text(), 'business_name');
            $schema[$this->key('cf')] = $this->formField(FormField::key($this->key('cf'))->text(), 'cf');
            $schema[$this->key('pi')] = $this->formField(FormField::key($this->key('pi'))->text(), 'pi');
            $schema[$this->key('sdi')] = $this->formField(FormField::key($this->key('sdi'))->text(), 'sdi');
            $schema[$this->key('pec')] = $this->formField(FormField::key($this->key('pec'))->email(), 'pec');
        }

        $countryField = FormField::key($this->key('country'))
            ->country($this->key('province'));

        if ($country !== null) {
            $countryField->value($country);
        }

        if ($this->allowedCountries !== []) {
            $countryField->options($this->countryOptions());
        }

        $schema[$this->key('country')] = $this->formField($countryField, 'country');
        $schema[$this->key('province')] = $this->formField(
            FormField::key($this->key('province'))->states($country),
            'province'
        );
        $schema[$this->key('city')] = $this->formField(FormField::key($this->key('city'))->text(), 'city');
        $schema[$this->key('cap')] = $this->formField(FormField::key($this->key('cap'))->text(), 'cap');
        $schema[$this->key('street')] = $this->formField(FormField::key($this->key('street'))->text(), 'street');
        $schema[$this->key('number')] = $this->formField(FormField::key($this->key('number'))->text(), 'number');

        if ($this->withMore) {
            $schema[$this->key('more')] = $this->formField(FormField::key($this->key('more'))->text(), 'more');
        }

        if ($this->withPhone) {
            $schema[$this->key('phone_prefix')] = $this->formField(FormField::key($this->key('phone_prefix'))->phonePrefix(), 'phone_prefix');
            $schema[$this->key('phone')] = $this->formField(FormField::key($this->key('phone'))->tel(), 'phone');
        }

        if ($this->withLink) {
            $schema[$this->key($this->linkKey)] = $this->formField(
                FormField::key($this->key($this->linkKey))->url(),
                'link',
                $this->linkKey
            );
        }

        return $schema;
    }

    public function keys(): array
    {
        return array_keys($this->labels());
    }

    public function decorate(array $row): array
    {
        $address = PrettifyAddress::prettifyRow(
            $row,
            $this->prefix !== '' ? $this->prefix : null,
            $this->mode,
        );

        $row[$this->computedKey('address')] = $address->line ?? '--';
        $row[$this->computedKey('prettyAddress')] = $address->pretty ?? '--';
        $row[$this->computedKey('prettyPDF')] = $address->prettyPDF ?? '--';

        if (property_exists($address, 'prettyPhone')) {
            $row[$this->computedKey('prettyPhone')] = $address->prettyPhone ?? '';
        }

        return $row;
    }

    public function decorateKeys(): array
    {
        $keys = [
            'address' => $this->computedKey('address'),
            'prettyAddress' => $this->computedKey('prettyAddress'),
            'prettyPDF' => $this->computedKey('prettyPDF'),
        ];

        if ($this->withPhone || $this->withContactName || $this->withType || $this->withCompanyData) {
            $keys['prettyPhone'] = $this->computedKey('prettyPhone');
        }

        return $keys;
    }

    private function key(string $name): string
    {
        $name = $this->normalizeFieldName($name);

        return $this->prefix !== '' ? $this->prefix.'_'.$name : $name;
    }

    private function computedKey(string $name): string
    {
        return $this->prefix !== '' ? $this->prefix.'_'.$name : $name;
    }

    private function dataField(object $field, string ...$aliases): object
    {
        if ($this->isRequiredField(...$aliases) && method_exists($field, 'required')) {
            $field->required();
        }

        return $field;
    }

    private function formField(FormField $field, string ...$aliases): FormField
    {
        if ($this->isRequiredField(...$aliases)) {
            $field->required();
        }

        return $field;
    }

    private function linkLabel(): string
    {
        return $this->linkKey === 'gmaps'
            ? (string) __t('components.forms.fields.gmaps.label')
            : (string) __t('components.forms.fields.link.label');
    }

    private function normalizePrefix(?string $prefix): string
    {
        return $this->normalizeFieldName($prefix ?? '', '');
    }

    private function normalizeFieldName(string $value, string $fallback = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? $value : $fallback;
    }

    private function normalizeCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $country = strtoupper(trim($country));

        return $country !== '' ? $country : null;
    }

    /**
     * @param array<int, string> $countries
     * @return list<string>
     */
    private function normalizeCountries(array $countries): array
    {
        $normalized = [];

        foreach ($countries as $country) {
            $value = $this->normalizeCountry($country);

            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, string>|string $fields
     * @return list<string>
     */
    private function normalizeFieldList(array|string $fields): array
    {
        $fields = is_array($fields) ? $fields : [$fields];
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->normalizeFieldName((string) $field);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function isRequiredField(string ...$aliases): bool
    {
        if ($this->requiredFields === []) {
            return false;
        }

        foreach ($aliases as $alias) {
            $alias = $this->normalizeFieldName($alias);

            if ($alias !== '' && in_array($alias, $this->requiredFields, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolveCountry(?string $country): ?string
    {
        $country = $this->normalizeCountry($country) ?? $this->countryDefault;

        if ($country !== null && $this->isAllowedCountry($country)) {
            return $country;
        }

        return $this->allowedCountries[0] ?? $country;
    }

    private function isAllowedCountry(?string $country): bool
    {
        if ($country === null || $this->allowedCountries === []) {
            return true;
        }

        return in_array($country, $this->allowedCountries, true);
    }

    private function countryOptions(): array
    {
        if (!function_exists('countries')) {
            return [];
        }

        $countries = (array) countries();
        $options = [];

        foreach ($this->allowedCountries as $country) {
            if (isset($countries[$country])) {
                $options[$country] = $countries[$country];
            }
        }

        return $options;
    }
}
