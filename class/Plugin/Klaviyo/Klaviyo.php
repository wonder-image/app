<?php

    namespace Wonder\Plugin\Klaviyo;

    use BadMethodCallException;
    use InvalidArgumentException;
    use KlaviyoAPI\KlaviyoAPI as KlaviyoClient;
    use KlaviyoAPI\Subclient;
    use ReflectionMethod;
    use ReflectionParameter;
    use Wonder\App\Credentials;

    abstract class Klaviyo extends KlaviyoClient {

        protected static ?string $apiKey = null;

        public array $params = [], $opts = [];

        public function __construct(
            ?string $apiKey = null,
            int $numRetries = 3,
            ?int $waitSeconds = null,
            array $guzzleOptions = [],
            string $userAgentSuffix = ''
        ) {

            static::$apiKey = $apiKey ?? (Credentials::api()->klaviyo_api_key ?? '');

            parent::__construct(static::$apiKey, $numRetries, $waitSeconds, $guzzleOptions, $userAgentSuffix);

        }

        public static function connect(
            ?string $apiKey = null,
            int $numRetries = 3,
            ?int $waitSeconds = null,
            array $guzzleOptions = [],
            string $userAgentSuffix = ''
        ): static {

            return new static($apiKey, $numRetries, $waitSeconds, $guzzleOptions, $userAgentSuffix);

        }

        public static function apiKey(
            string $apiKey,
            int $numRetries = 3,
            ?int $waitSeconds = null,
            array $guzzleOptions = [],
            string $userAgentSuffix = ''
        ): static {

            static::$apiKey = $apiKey;

            return new static($apiKey, $numRetries, $waitSeconds, $guzzleOptions, $userAgentSuffix);

        }

        abstract public function object(): Subclient;

        public function __call(string $method, array $arguments)
        {

            $resource = $this->object();
            $apiInstance = $resource->api_instance ?? null;

            if (!is_object($apiInstance) || !method_exists($apiInstance, $method)) {
                throw new BadMethodCallException("Method [$method] not found on ".static::class.'.');
            }

            return $resource->$method(...$this->prepareArguments($apiInstance, $method, $arguments));

        }

        public function addParams(string $key, $value): static
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

        public function pushParams(string $key, $value): static
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

        public function addOptions(string $key, $value): static
        {

            $this->opts[$key] = $value;

            return $this;

        }

        public function param(string $key, $value): static
        {

            return $this->addParams($key, $value);

        }

        public function option(string $key, $value): static
        {

            return $this->addOptions($key, $value);

        }

        public function body(array $value, ?string $parameter = null): static
        {

            if ($parameter !== null) {
                return $this->addParams($parameter, $value);
            }

            $this->params = array_replace_recursive($this->params, $value);

            return $this;

        }

        public function bodyParam(string $key, $value): static
        {

            return $this->addParams($key, $value);

        }

        public function includes(array|string $value): static
        {

            if (is_array($value)) {
                return $this->addParams('include', $value);
            }

            return $this->pushParams('include', $value);

        }

        public function filter(string $value): static
        {

            return $this->addParams('filter', $value);

        }

        public function sort(string $value): static
        {

            return $this->addParams('sort', $value);

        }

        public function pageCursor(string $value): static
        {

            return $this->addParams('page_cursor', $value);

        }

        public function pageSize(int $value): static
        {

            return $this->addParams('page_size', $value);

        }

        public function fields(string $resource, array|string $value): static
        {

            $key = $this->resourceParameter('fields', $resource);

            if (is_array($value)) {
                return $this->addParams($key, $value);
            }

            return $this->pushParams($key, $value);

        }

        public function field(string $resource, string $value): static
        {

            return $this->pushParams($this->resourceParameter('fields', $resource), $value);

        }

        public function additionalFields(string $resource, array|string $value): static
        {

            $key = $this->resourceParameter('additional_fields', $resource);

            if (is_array($value)) {
                return $this->addParams($key, $value);
            }

            return $this->pushParams($key, $value);

        }

        public function additionalField(string $resource, string $value): static
        {

            return $this->pushParams($this->resourceParameter('additional_fields', $resource), $value);

        }

        public function contentType(string $value): static
        {

            return $this->addOptions('contentType', $value);

        }

        public function apiKeyOverride(string $value): static
        {

            return $this->addOptions('apiKey', $value);

        }

        public function clear(): static
        {

            $this->params = [];
            $this->opts = [];

            return $this;

        }

        public function reset(): static
        {

            return $this->clear();

        }

        protected function prepareArguments(object $apiInstance, string $method, array $arguments): array
        {

            $reflection = new ReflectionMethod($apiInstance, $method);
            $parameters = $reflection->getParameters();
            $parameterNames = array_map(static fn (ReflectionParameter $parameter) => $parameter->getName(), $parameters);

            $resolved = [];
            $bodyPayload = [];
            $namedArguments = [];
            $positionalArguments = [];

            foreach ($arguments as $key => $value) {

                if (is_string($key)) {
                    $namedArguments[$key] = $value;
                } else {
                    $positionalArguments[] = $value;
                }

            }

            $recognizedNamedArguments = array_intersect_key($namedArguments, array_flip($parameterNames));
            $bodyPayload = array_diff_key($namedArguments, $recognizedNamedArguments);

            $resolved = $recognizedNamedArguments;

            $position = 0;

            foreach ($parameters as $parameter) {

                $name = $parameter->getName();

                if (array_key_exists($name, $resolved)) {
                    continue;
                }

                if (array_key_exists($position, $positionalArguments)) {
                    $resolved[$name] = $positionalArguments[$position];
                    $position++;
                }

            }

            if ($position < count($positionalArguments)) {
                throw new InvalidArgumentException("Too many arguments passed to [$method] on ".static::class.'.');
            }

            foreach ($this->params as $key => $value) {

                if (in_array($key, $parameterNames, true)) {

                    if (!array_key_exists($key, $resolved)) {
                        $resolved[$key] = $value;
                    }

                    continue;

                }

                $bodyPayload[$key] = $value;

            }

            foreach ($this->opts as $key => $value) {

                if (!in_array($key, $parameterNames, true) || array_key_exists($key, $resolved)) {
                    continue;
                }

                $resolved[$key] = $value;

            }

            $bodyParameter = $this->bodyParameter($parameters);

            if ($bodyParameter !== null && !empty($bodyPayload)) {

                if (isset($resolved[$bodyParameter]) && is_array($resolved[$bodyParameter])) {
                    $resolved[$bodyParameter] = array_replace_recursive($bodyPayload, $resolved[$bodyParameter]);
                } elseif (!isset($resolved[$bodyParameter])) {
                    $resolved[$bodyParameter] = $bodyPayload;
                } else {
                    throw new InvalidArgumentException("Unable to merge body parameters for [$method] on ".static::class.'.');
                }

            }

            if ($bodyParameter === null && !empty($bodyPayload)) {
                throw new InvalidArgumentException(
                    'Unknown parameters ['.implode(', ', array_keys($bodyPayload))."] passed to [$method] on ".static::class.'.'
                );
            }

            return $resolved;

        }

        protected function bodyParameter(array $parameters): ?string
        {

            foreach ($parameters as $parameter) {

                $name = $parameter->getName();

                if (preg_match('/(?:query|request)$/', $name) === 1) {
                    return $name;
                }

            }

            return null;

        }

        protected function resourceParameter(string $prefix, string $resource): string
        {

            if (str_starts_with($resource, $prefix.'_')) {
                return $resource;
            }

            $resource = strtolower($resource);
            $resource = preg_replace('/[^a-z0-9]+/', '_', $resource) ?? $resource;
            $resource = trim($resource, '_');

            return $prefix.'_'.$resource;

        }

    }
