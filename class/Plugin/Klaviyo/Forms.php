<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\FormsApi
     */
    class Forms extends Klaviyo {

        protected const FORM_TYPE = 'form';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Forms;

        }

        public function all()
        {

            return $this->getForms();

        }

        public function get($formId)
        {

            return $this->getForm($formId);

        }

        public function create()
        {

            return $this->createForm();

        }

        public function delete($formId)
        {

            return $this->deleteForm($formId);

        }

        public function name($value): static
        {

            $this->dataType(self::FORM_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function definition(array $value): static
        {

            $this->dataType(self::FORM_TYPE);

            return $this->dataAttribute('definition', $value);

        }

        public function status($value): static
        {

            $this->dataType(self::FORM_TYPE);

            return $this->dataAttribute('status', $value);

        }

        public function abTest(bool $value = true): static
        {

            $this->dataType(self::FORM_TYPE);

            return $this->dataAttribute('ab_test', $value);

        }

    }
