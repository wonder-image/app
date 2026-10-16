<?php

namespace Wonder\Api\Token;

use DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;
use Wonder\Api\Contracts\ApiUserProvider;
use Wonder\Api\EndpointException;
use Wonder\Http\UrlParser;

/**
 * Verifica un bearer JWT contro `api_users`, senza HTTP ne SQL diretti:
 * firma e scadenza, utente attivo, authority richiesta, riga token attiva,
 * confronto `hash_equals` con il token salvato (rotazione e revoca hanno
 * effetto subito), IP e domini ammessi.
 *
 * Un token di pagina (`typ = page`) non coincide col token salvato: vale se
 * ha `exp`, se `tid` e la riga trovata e se `tkh` e l'impronta del token
 * salvato in quel momento. Gli endpoint solo server lo rifiutano con
 * `$allowPageToken = false`.
 *
 * Rifiuti: `EndpointException` 401 (token) o 403 (authority).
 */
final class BearerVerifier
{
    private ApiUserProvider $users;

    public function __construct(
        private readonly string $key,
        ?ApiUserProvider $users = null,
        private readonly ?bool $requireExp = null,
    ) {
        $this->users = $users ?? new InfoUserProvider();
    }

    public static function make(): self
    {
        return new self(\Wonder\App\Credentials::appKey());
    }

    /**
     * @param list<string> $authorities authority ammesse dall'endpoint (la prima posseduta vince)
     * @throws EndpointException
     */
    public function verify(
        string $bearer,
        array $authorities,
        ?string $ip = null,
        ?string $domain = null,
        bool $allowPageToken = true,
        ?int $now = null,
    ): VerifiedBearer {
        $claims = $this->decode($bearer, $now);
        $isPage = ($claims->typ ?? null) === ApiTokens::PAGE_TYPE;

        if (isset($claims->typ) && !$isPage) {
            throw new EndpointException('Token non valido', 401);
        }

        if ($isPage && !$allowPageToken) {
            throw new EndpointException('Token di pagina non ammesso per questo endpoint', 401);
        }

        if (($isPage || ($this->requireExp ?? TokenConfig::requireExp())) && !isset($claims->exp)) {
            throw new EndpointException('Token senza scadenza', 401);
        }

        $sub = $claims->sub ?? null;

        if (!is_int($sub) && !(is_string($sub) && $sub !== '')) {
            throw new EndpointException('Token non valido', 401);
        }

        $user = $this->users->user($sub);

        if (!is_object($user) || empty($user->exists)) {
            throw new EndpointException('Utente non valido', 401);
        }

        if (!in_array($user->active ?? null, [true, 'true'], true)) {
            throw new EndpointException('Utente non attivo', 401);
        }

        $owned = array_map('strval', (array) ($user->authority ?? []));
        $matched = null;

        foreach ($authorities as $authority) {
            if (in_array((string) $authority, $owned, true)) {
                $matched = (string) $authority;
                break;
            }
        }

        if ($matched === null) {
            throw new EndpointException('Utente non autorizzato per questo endpoint!', 403);
        }

        $token = $user->{$matched} ?? null;

        if (!is_object($token) || empty($token->exists)) {
            throw new EndpointException('Token non valido', 401);
        }

        $stored = (string) ($token->token ?? '');
        $valid = $isPage
            ? $stored !== ''
                && (string) ($claims->tid ?? '') === (string) ($token->id ?? '')
                && hash_equals(ApiTokens::binding($stored), (string) ($claims->tkh ?? ''))
            : $stored !== '' && hash_equals($stored, $bearer);

        if (!$valid) {
            throw new EndpointException('Token non valido', 401);
        }

        if ((string) ($token->active ?? '') !== 'true') {
            throw new EndpointException('Token non attivo', 401);
        }

        $ips = self::list($token->allowed_ips ?? []);

        if ($ips !== [] && !in_array((string) $ip, $ips, true)) {
            throw new EndpointException('Token non valido per questo IP', 401);
        }

        $domains = self::list($token->allowed_domains ?? []);

        if ($domains !== [] && !UrlParser::matchesAnyDomain((string) $domain, $domains)) {
            throw new EndpointException('Token non valido per questo dominio', 401);
        }

        return new VerifiedBearer(
            $user,
            $sub,
            $matched,
            $token,
            $token->id ?? null,
            $isPage ? VerifiedBearer::KIND_PAGE : VerifiedBearer::KIND_API,
            isset($claims->exp) ? (int) $claims->exp : null,
        );
    }

    private function decode(string $bearer, ?int $now): object
    {
        $previous = JWT::$timestamp;
        JWT::$timestamp = $now ?? $previous;

        try {
            return JWT::decode($bearer, new Key($this->key, 'HS256'));
        } catch (ExpiredException) {
            throw new EndpointException('Token scaduto', 401);
        } catch (UnexpectedValueException | DomainException) {
            throw new EndpointException('Token non valido', 401);
        } finally {
            JWT::$timestamp = $previous;
        }
    }

    /** @return list<string> */
    public static function list(mixed $values): array
    {
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$values];
        }

        $out = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if ((is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                $out[] = trim((string) $value);
            }
        }

        return array_values(array_unique($out));
    }
}
