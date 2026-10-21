<?php

    namespace Wonder\Plugin\Google\Security;

    use Closure;
    use Wonder\Api\Call;
    use Wonder\Plugin\Google\Credentials;

    /**
     * Chiamata a reCAPTCHA Enterprise (assessments). Oltre a `valid` dice se
     * Google ha risposto davvero (`available`): la guardia salta la verifica
     * quando non ha risposto (rete, 429, 5xx, altri 4xx). 401/403 contano come
     * risposta: chiave API o progetto sbagliati sono un errore di configurazione.
     */
    class reCAPTCHA {

        protected static $projectId;
        protected static $apiKey;
        public static $siteKey;
        protected static $action;

        private Closure $callFactory;

        public function __construct(?Closure $callFactory = null, ?object $credentials = null) {

            $credentials ??= Credentials::get();

            self::$projectId = $credentials->gcp_project_id;
            self::$apiKey = $credentials->gcp_api_key;
            self::$siteKey = $credentials->recaptcha_site_key;

            $this->callFactory = $callFactory ?? fn (string $url, array $event) => new Call($url, $event);

        }

        /** `$siteKey` null = chiave a casella (v2). */
        public function verify(string $token, string $action, ?string $siteKey = null): object {

            $event = [
                'event' => [
                    'token' => $token,
                    'expectedAction' => $action,
                    'siteKey' => $siteKey ?? self::$siteKey
                ]
            ];

            $call = ($this->callFactory)('https://recaptchaenterprise.googleapis.com/v1/projects/'.self::$projectId.'/assessments?key='.self::$apiKey, $event);
            $call->contentType('application/json')->connectTimeout(3)->timeout(5);

            $response = $call->call();
            $decoded = ($response['success'] ?? false) ? json_decode((string) $response['result'], true) : null;

            return self::classify(is_array($decoded) ? $decoded : [], $call->httpCode());

        }

        /** Risposta di Google → `valid`, `score`, `action`, `available`, `config`, `httpCode`, `result`. */
        public static function classify(array $response, int $httpCode): object {

            $properties = (array) ($response['tokenProperties'] ?? []);
            $score = $response['riskAnalysis']['score'] ?? null;
            $action = trim((string) ($properties['action'] ?? ''));

            return (object) [
                'valid' => in_array($properties['valid'] ?? false, [true, 'true'], true),
                'score' => is_numeric($score) ? (float) $score : null,
                'action' => $action !== '' ? $action : null,
                'available' => ($httpCode === 200 && $response !== []) || in_array($httpCode, [400, 401, 403], true),
                'config' => in_array($httpCode, [401, 403], true),
                'httpCode' => $httpCode,
                'result' => $response,
            ];

        }

    }
