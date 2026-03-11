<?php

    namespace Wonder\Plugin\Brevo;

    use Brevo\Brevo as BrevoClient;
    use Wonder\App\Credentials;

    abstract class Brevo extends BrevoClient {

        private static $apiKey;

        public $params = [], $opts = [];

        public function __construct( $apiKey = null, array $options = [] ) {

            self::$apiKey = ($apiKey == null) ? Credentials::mail()->brevo_api_key : $apiKey;

            parent::__construct(self::$apiKey, $options);

        }

        public static function connect($apiKey = null, array $options = []): static
        {

            return new static($apiKey, $options);

        }

        public static function apiKey($apiKey, array $options = []): static
        {

            self::$apiKey = $apiKey;

            return new static($apiKey, $options);

        }

        public function addParams($key, $value): static
        {

            $keys = explode('.', $key);
            $target = &$this->params;

            foreach ($keys as $part) {

                if (!isset($target[$part]) || !is_array($target[$part])) {
                    $target[$part] = [];
                }

                $target = &$target[$part];

            }

            $target = $value;

            return $this;

        }

        public function pushParams($key, $value): static
        {

            $keys = explode('.', $key);
            $target = &$this->params;

            foreach ($keys as $part) {

                if (!isset($target[$part]) || !is_array($target[$part])) {
                    $target[$part] = [];
                }

                $target = &$target[$part];

            }

            array_push($target, $value);

            return $this;

        }

        public function addOptions($key, $value): static
        {

            $this->opts[$key] = $value;

            return $this;

        }

    }
