<?php

namespace Wonder\App\Schema\Extensions;

use InvalidArgumentException;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class AddressExtension
{
    private string $prefix;
    private string $mode;
    private string $linkKey;
    private ?string $countryDefault;

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
            Field::key($this->key('country'))->text(),
            Field::key($this->key('province'))->text(),
            Field::key($this->key('city'))->text(),
            Field::key($this->key('cap'))->text(),
            Field::key($this->key('street'))->text(),
            Field::key($this->key('number'))->text(),
        ];

        if ($this->withMore) {
            $fields[] = Field::key($this->key('more'))->text();
        }

        if ($this->withLink) {
            $fields[] = Field::key($this->key($this->linkKey))->text();
        }

        if ($this->withPhone) {
            $fields[] = Field::key($this->key('phone_prefix'))->text();
            $fields[] = Field::key($this->key('phone'))->text();
        }

        if ($this->withContactName) {
            $fields[] = Field::key($this->key('name'))->text();
            $fields[] = Field::key($this->key('surname'))->text();
        }

        if ($this->withType) {
            $fields[] = Field::key($this->key('type'))->text()->lower();
        }

        if ($this->withCompanyData) {
            $fields[] = Field::key($this->key('business_name'))->text();
            $fields[] = Field::key($this->key('cf'))->tin()
                ->countryField($this->key('country'))
                ->type('all');
            $fields[] = Field::key($this->key('pi'))->vat()
                ->countryField($this->key('country'));
            $fields[] = Field::key($this->key('sdi'))->text();
            $fields[] = Field::key($this->key('pec'))->email();
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
        $country = $this->normalizeCountry($country) ?? $this->countryDefault;
        $schema = [];

        if ($this->withType) {
            $schema[$this->key('type')] = FormField::key($this->key('type'))
                ->select([
                    'private' => __t('components.forms.options.address_type.private'),
                    'business' => __t('components.forms.options.address_type.business'),
                ])
                ->value('private');
        }

        if ($this->withContactName) {
            $schema[$this->key('name')] = FormField::key($this->key('name'))->text();
            $schema[$this->key('surname')] = FormField::key($this->key('surname'))->text();
        }

        if ($this->withCompanyData) {
            $schema[$this->key('business_name')] = FormField::key($this->key('business_name'))->text();
            $schema[$this->key('cf')] = FormField::key($this->key('cf'))->text();
            $schema[$this->key('pi')] = FormField::key($this->key('pi'))->text();
            $schema[$this->key('sdi')] = FormField::key($this->key('sdi'))->text();
            $schema[$this->key('pec')] = FormField::key($this->key('pec'))->email();
        }

        $schema[$this->key('country')] = FormField::key($this->key('country'))
            ->country($this->key('province'));
        $schema[$this->key('province')] = FormField::key($this->key('province'))
            ->states($country);
        $schema[$this->key('city')] = FormField::key($this->key('city'))->text();
        $schema[$this->key('cap')] = FormField::key($this->key('cap'))->text();
        $schema[$this->key('street')] = FormField::key($this->key('street'))->text();
        $schema[$this->key('number')] = FormField::key($this->key('number'))->text();

        if ($this->withMore) {
            $schema[$this->key('more')] = FormField::key($this->key('more'))->text();
        }

        if ($this->withPhone) {
            $schema[$this->key('phone_prefix')] = FormField::key($this->key('phone_prefix'))->phonePrefix();
            $schema[$this->key('phone')] = FormField::key($this->key('phone'))->tel();
        }

        if ($this->withLink) {
            $schema[$this->key($this->linkKey)] = FormField::key($this->key($this->linkKey))->url();
        }

        return $schema;
    }

    public function keys(): array
    {
        return array_keys($this->labels());
    }

    private function key(string $name): string
    {
        $name = $this->normalizeFieldName($name);

        return $this->prefix !== '' ? $this->prefix.'_'.$name : $name;
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
}
