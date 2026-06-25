<?php

    namespace Wonder\Plugin\Brevo;

    use Brevo\Account\Requests\GetAccountActivityRequest;

    class Account extends Brevo {

        public function object()
        {

            return parent::connect()->account;

        }

        public function get()
        {

            return $this->object()->getAccount($this->opts);

        }

        public function activity()
        {

            return $this->object()->getAccountActivity(new GetAccountActivityRequest($this->params), $this->opts);

        }

        public function startDate($value): static
        {

            return $this->addParams('startDate', $value);

        }

        public function endDate($value): static
        {

            return $this->addParams('endDate', $value);

        }

        public function email($value): static
        {

            return $this->addParams('email', $value);

        }

        public function limit(int $value): static
        {

            return $this->addParams('limit', $value);

        }

        public function offset(int $value): static
        {

            return $this->addParams('offset', $value);

        }

    }
