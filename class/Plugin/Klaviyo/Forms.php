<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\FormsApi
     */
    class Forms extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Forms;

        }

    }
