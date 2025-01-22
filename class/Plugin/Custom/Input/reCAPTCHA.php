<?php

    namespace Wonder\Plugin\Custom\Input;
    use Wonder\Plugin\Google\Security\reCAPTCHA as Credentials;

    class reCAPTCHA {

        public $definition = [];

        private function set($key, $value) {

            if (!empty($value) && !is_null($value)) {
                $this->definition[$key] = $value;
            }
            
        }

        public function action($value):reCAPTCHA { $this->set('action', $value); return $this; }

        /**
         * Summary of theme
         * @param mixed $value = light || dark
         * @return reCAPTCHA
         */
        public function theme($value):reCAPTCHA { $this->set('theme', $value); return $this; }

        /**
         * Summary of size
         * @param mixed $value = compact || normal
         * @return reCAPTCHA
         */
        public function size($value):reCAPTCHA { $this->set('size', $value); return $this; }

        public function generate() {

            $siteKey = (new Credentials())::$siteKey;

            $action = $this->definition['action'] ?? '';
            $theme = $this->definition['theme'] ?? 'light';
            $size = $this->definition['size'] ?? 'normal';

            return "
            <div class=\"g-recaptcha\" data-wi-site-key=\"$siteKey\" data-wi-theme=\"$theme\" data-wi-size=\"$size\" data-wi-action=\"$action\"></div>
            <input type=\"hidden\" name=\"g-recaptcha-token\" required>
            <input type=\"hidden\" name=\"g-recaptcha-action\" required>";
            
        }

    }