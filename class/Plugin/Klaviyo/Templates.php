<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TemplatesApi
     */
    class Templates extends Klaviyo {

        protected const TEMPLATE_TYPE = 'template';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Templates;

        }

        public function all()
        {

            return $this->getTemplates();

        }

        public function get($templateId)
        {

            return $this->getTemplate($templateId);

        }

        public function create()
        {

            return $this->createTemplate();

        }

        public function update($templateId)
        {

            return $this->updateTemplate($templateId);

        }

        public function delete($templateId)
        {

            return $this->deleteTemplate($templateId);

        }

        public function name($value): static
        {

            $this->dataType(self::TEMPLATE_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function editorType($value): static
        {

            $this->dataType(self::TEMPLATE_TYPE);

            return $this->dataAttribute('editor_type', $value);

        }

        public function html($value): static
        {

            $this->dataType(self::TEMPLATE_TYPE);

            return $this->dataAttribute('html', $value);

        }

        public function text($value): static
        {

            $this->dataType(self::TEMPLATE_TYPE);

            return $this->dataAttribute('text', $value);

        }

        public function amp($value): static
        {

            $this->dataType(self::TEMPLATE_TYPE);

            return $this->dataAttribute('amp', $value);

        }

        public function context(array $value): static
        {

            return $this->addParams('data.attributes.context', $value);

        }

    }
