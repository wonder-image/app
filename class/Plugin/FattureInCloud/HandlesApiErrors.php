<?php

    namespace Wonder\Plugin\FattureInCloud;

    trait HandlesApiErrors
    {

        protected function guard(string $action, callable $callback): mixed
        {

            try {

                return $callback();

            } catch (\FattureInCloud\ApiException $e) {

                \logFattureInCloudError($action, $e);
                throw $e;

            } catch (\Throwable $e) {

                \__log($e, 'fatture-in-cloud', $action, 'ERROR', 'error/fatture-in-cloud');
                throw $e;

            }

        }

    }
