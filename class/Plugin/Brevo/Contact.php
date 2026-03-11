<?php

    namespace Wonder\Plugin\Brevo;

    use Brevo\Contacts\Requests\CreateContactRequest;
    use Brevo\Contacts\Requests\DeleteContactRequest;
    use Brevo\Contacts\Requests\GetContactInfoRequest;
    use Brevo\Contacts\Requests\GetContactsRequest;
    use Brevo\Contacts\Requests\UpdateContactRequest;

    class Contact extends Brevo {

        public function object()
        {

            return parent::connect()->contacts;

        }

        public function all()
        {

            return $this->object()->getContacts(new GetContactsRequest($this->params), $this->opts);

        }

        public function create()
        {

            return $this->object()->createContact(new CreateContactRequest($this->params), $this->opts);

        }

        public function get($identifier)
        {

            return $this->object()->getContactInfo($identifier, new GetContactInfoRequest($this->params), $this->opts);

        }

        public function update($identifier)
        {

            return $this->object()->updateContact($identifier, new UpdateContactRequest($this->params), $this->opts);

        }

        public function delete($identifier)
        {

            return $this->object()->deleteContact($identifier, new DeleteContactRequest($this->params), $this->opts);

        }

        public function email($value): static
        {

            return $this->addParams('email', $value);

        }

        public function extId($value): static
        {

            return $this->addParams('extId', $value);

        }

        public function identifierType($value): static
        {

            return $this->addParams('identifierType', $value);

        }

        public function attribute(string $key, $value): static
        {

            return $this->addParams("attributes.$key", $value);

        }

        public function firstName($value): static
        {

            return $this->attribute('FNAME', $value);

        }

        public function lastName($value): static
        {

            return $this->attribute('LNAME', $value);

        }

        public function phone($value): static
        {

            return $this->attribute('SMS', $value);

        }

        public function whatsapp($value): static
        {

            return $this->attribute('WHATSAPP', $value);

        }

        public function listId(int $value): static
        {

            return $this->pushParams('listIds', $value);

        }

        public function listIds(array $value): static
        {

            return $this->addParams('listIds', $value);

        }

        public function unlinkListId(int $value): static
        {

            return $this->pushParams('unlinkListIds', $value);

        }

        public function emailBlacklisted(bool $value = true): static
        {

            return $this->addParams('emailBlacklisted', $value);

        }

        public function smsBlacklisted(bool $value = true): static
        {

            return $this->addParams('smsBlacklisted', $value);

        }

        public function updateEnabled(bool $value = true): static
        {

            return $this->addParams('updateEnabled', $value);

        }

        public function smtpBlacklistSender($value): static
        {

            return $this->pushParams('smtpBlacklistSender', $value);

        }

        public function limit(int $value): static
        {

            return $this->addParams('limit', $value);

        }

        public function offset(int $value): static
        {

            return $this->addParams('offset', $value);

        }

        public function sort($value): static
        {

            return $this->addParams('sort', $value);

        }

        public function filter($value): static
        {

            return $this->addParams('filter', $value);

        }

        public function segmentId(int $value): static
        {

            return $this->addParams('segmentId', $value);

        }

        public function createdSince($value): static
        {

            return $this->addParams('createdSince', $value);

        }

        public function modifiedSince($value): static
        {

            return $this->addParams('modifiedSince', $value);

        }

        public function startDate($value): static
        {

            return $this->addParams('startDate', $value);

        }

        public function endDate($value): static
        {

            return $this->addParams('endDate', $value);

        }

    }
