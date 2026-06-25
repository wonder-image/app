<?php

    namespace Wonder\Plugin\Brevo;

    use Brevo\Companies\Requests\GetCompaniesRequest;
    use Brevo\Companies\Requests\PatchCompaniesIdRequest;
    use Brevo\Companies\Requests\PatchCompaniesLinkUnlinkIdRequest;
    use Brevo\Companies\Requests\PostCompaniesRequest;

    class Company extends Brevo {

        public function object()
        {

            return parent::connect()->companies;

        }

        public function all()
        {

            return $this->object()->getAllCompanies(new GetCompaniesRequest($this->params), $this->opts);

        }

        public function create()
        {

            return $this->object()->createACompany(new PostCompaniesRequest($this->params), $this->opts);

        }

        public function get($identifier)
        {

            return $this->object()->getACompany($identifier, $this->opts);

        }

        public function update($identifier)
        {

            return $this->object()->updateACompany($identifier, new PatchCompaniesIdRequest($this->params), $this->opts);

        }

        public function link($identifier)
        {

            return $this->object()->linkAndUnlinkCompanyWithContactAndDeal($identifier, new PatchCompaniesLinkUnlinkIdRequest($this->params), $this->opts);

        }

        public function delete($identifier)
        {

            return $this->object()->deleteACompany($identifier, $this->opts);

        }

        public function name($value): static
        {

            return $this->addParams('name', $value);

        }

        public function attribute(string $key, $value): static
        {

            return $this->addParams("attributes.$key", $value);

        }

        public function countryCode(int $value): static
        {

            return $this->addParams('countryCode', $value);

        }

        public function linkedContactId(int $value): static
        {

            return $this->pushParams('linkedContactsIds', $value);

        }

        public function linkedContactsIds(array $value): static
        {

            return $this->addParams('linkedContactsIds', $value);

        }

        public function filterLinkedContactId(int $value): static
        {

            return $this->addParams('linkedContactsIds', $value);

        }

        public function linkContactId(int $value): static
        {

            return $this->pushParams('linkContactIds', $value);

        }

        public function unlinkContactId(int $value): static
        {

            return $this->pushParams('unlinkContactIds', $value);

        }

        public function filters($value): static
        {

            return $this->addParams('filters', $value);

        }

        public function page(int $value): static
        {

            return $this->addParams('page', $value);

        }

        public function limit(int $value): static
        {

            return $this->addParams('limit', $value);

        }

        public function sort($value): static
        {

            return $this->addParams('sort', $value);

        }

        public function sortBy($value): static
        {

            return $this->addParams('sortBy', $value);

        }

        public function createdSince($value): static
        {

            return $this->addParams('createdSince', $value);

        }

        public function modifiedSince($value): static
        {

            return $this->addParams('modifiedSince', $value);

        }

    }
