<?php

namespace Wonder\Api\Token;

/** Esito positivo di `BearerVerifier::verify()`. */
final class VerifiedBearer
{
    public const KIND_API = 'api';
    public const KIND_PAGE = 'page';

    public function __construct(
        public readonly object $user,
        public readonly int|string $userId,
        public readonly string $authority,
        public readonly object $token,
        public readonly int|string|null $tokenId,
        public readonly string $kind,
        public readonly ?int $expiresAt,
    ) {}

    public function isPageToken(): bool
    {
        return $this->kind === self::KIND_PAGE;
    }
}
