<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\FlowsApi
     */
    class Flows extends Klaviyo {

        protected const FLOW_TYPE = 'flow';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Flows;

        }

        public function all()
        {

            return $this->getFlows();

        }

        public function get($flowId)
        {

            return $this->getFlow($flowId);

        }

        public function create()
        {

            return $this->createFlow();

        }

        public function update($flowId)
        {

            return $this->updateFlow($flowId);

        }

        public function delete($flowId)
        {

            return $this->deleteFlow($flowId);

        }

        public function name($value): static
        {

            $this->dataType(self::FLOW_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function definition(array $value): static
        {

            $this->dataType(self::FLOW_TYPE);

            return $this->dataAttribute('definition', $value);

        }

        public function status($value): static
        {

            $this->dataType(self::FLOW_TYPE);

            return $this->dataAttribute('status', $value);

        }

    }
