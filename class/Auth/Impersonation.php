<?php

namespace Wonder\Auth;

use InvalidArgumentException;

final class Impersonation
{
    private const SESSION_KEY = 'auth_impersonation';
    private const CSRF_KEY = 'auth_impersonation_csrf';

    /** @param list<string> $allowedActorAuthorities */
    public function __construct(
        private readonly array $allowedActorAuthorities,
        private readonly int $tokenTtlSeconds = 120,
        private readonly int $maxDurationSeconds = 1800,
    ) {}

    public function issue(
        int $actorUserId,
        int $subjectUserId,
        ?string $continueUrl = null,
        ?string $returnUrl = null,
        ?string $stopUrl = null,
    ): object {
        $this->assertEligiblePair($actorUserId, $subjectUserId);

        $issued = (new OneTimeToken('impersonation', $this->tokenTtlSeconds))->issue(
            $subjectUserId,
            $actorUserId,
            $continueUrl,
            [
                'return_url' => $returnUrl,
                'stop_url' => $stopUrl,
                // Il token vale solo nella sessione che l'ha emesso.
                'actor_session' => self::sessionFingerprint(),
            ]
        );

        $this->audit('issued', $actorUserId, $subjectUserId, [
            'selector' => $issued->selector,
        ]);

        return $issued;
    }

    public function start(string $token): object
    {
        if ($this->current() !== null) {
            return (object) ['success' => false, 'reason' => 'impersonation_already_active'];
        }

        $consumed = (new OneTimeToken('impersonation', $this->tokenTtlSeconds))->consume($token);

        if ($consumed === null || $consumed->actor_user_id === null) {
            $this->audit('failed', null, null, ['reason' => 'token_invalid']);
            return (object) ['success' => false, 'reason' => 'impersonation_token_invalid'];
        }

        $actorSession = (string) ($consumed->metadata['actor_session'] ?? '');

        if ($actorSession === '' || !hash_equals($actorSession, self::sessionFingerprint())) {
            $this->audit('start_denied', $consumed->actor_user_id, $consumed->subject_user_id, [
                'reason' => 'session_mismatch',
            ]);

            return (object) ['success' => false, 'reason' => 'impersonation_session_mismatch'];
        }

        try {
            $this->assertEligiblePair($consumed->actor_user_id, $consumed->subject_user_id);
        } catch (\Throwable $exception) {
            $this->audit('failed', $consumed->actor_user_id, $consumed->subject_user_id, [
                'reason' => $exception->getMessage(),
            ]);

            return (object) ['success' => false, 'reason' => $exception->getMessage()];
        }

        $_SESSION[self::SESSION_KEY] = [
            'actor_user_id' => $consumed->actor_user_id,
            'subject_user_id' => $consumed->subject_user_id,
            'started_at' => time(),
            'expires_at' => time() + max(60, $this->maxDurationSeconds),
            'return_url' => (string) ($consumed->metadata['return_url'] ?? ''),
            'stop_url' => (string) ($consumed->metadata['stop_url'] ?? ''),
        ];
        $_SESSION['user_id'] = $consumed->subject_user_id;

        $this->regenerateSession();
        $this->audit('started', $consumed->actor_user_id, $consumed->subject_user_id);

        return (object) [
            'success' => true,
            'actor_user_id' => $consumed->actor_user_id,
            'subject_user_id' => $consumed->subject_user_id,
            'continue_url' => $consumed->continue_url,
        ];
    }

    public function stop(string $csrfToken): object
    {
        $current = $this->current();

        if ($current === null) {
            return (object) ['success' => false, 'reason' => 'impersonation_not_active'];
        }

        if (!$this->verifyCsrf($csrfToken, 'stop')) {
            $this->audit('refused', $current->actor_user_id, $current->subject_user_id, [
                'reason' => 'csrf_invalid',
            ]);

            return (object) ['success' => false, 'reason' => 'impersonation_csrf_invalid'];
        }

        if (!$this->restoreActor($current)) {
            $this->audit('failed', $current->actor_user_id, $current->subject_user_id, [
                'reason' => 'actor_no_longer_active',
            ]);

            return (object) ['success' => false, 'reason' => 'impersonation_actor_invalid'];
        }

        $this->audit('stopped', $current->actor_user_id, $current->subject_user_id, [
            'duration_seconds' => max(0, time() - $current->started_at),
        ]);

        return (object) [
            'success' => true,
            'actor_user_id' => $current->actor_user_id,
            'subject_user_id' => $current->subject_user_id,
            'return_url' => $current->return_url,
        ];
    }

    public function current(): ?object
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($state)) {
            return null;
        }

        $actorUserId = (int) ($state['actor_user_id'] ?? 0);
        $subjectUserId = (int) ($state['subject_user_id'] ?? 0);

        if ($actorUserId <= 0 || $subjectUserId <= 0 || (int) ($_SESSION['user_id'] ?? 0) !== $subjectUserId) {
            return null;
        }

        $startedAt = (int) ($state['started_at'] ?? 0);
        $current = (object) [
            'actor_user_id' => $actorUserId,
            'subject_user_id' => $subjectUserId,
            'started_at' => $startedAt,
            'return_url' => (string) ($state['return_url'] ?? ''),
            'stop_url' => (string) ($state['stop_url'] ?? ''),
        ];

        // Sessioni avviate prima di `expires_at`: scadenza da `started_at`.
        $expiresAt = (int) ($state['expires_at'] ?? ($startedAt + max(60, $this->maxDurationSeconds)));

        if (time() >= $expiresAt) {
            $restored = $this->restoreActor($current);
            $this->audit('expired', $actorUserId, $subjectUserId, [
                'duration_seconds' => max(0, time() - $startedAt),
                'actor_restored' => $restored,
            ]);

            return null;
        }

        return $current;
    }

    /**
     * Applica la scadenza a ogni richiesta, prima che l'utente venga risolto
     * (bootstrap `config/app/session.php`): vale anche per API e layout custom.
     */
    public static function enforceExpiry(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }

        // `current()` usa `expires_at` salvato in sessione e, se scaduto, ripristina l'attore.
        (new self([]))->current();
    }

    public function csrfToken(string $purpose = 'stop'): string
    {
        $purpose = preg_replace('/[^a-z0-9_-]/', '', strtolower($purpose)) ?: 'default';

        if (empty($_SESSION[self::CSRF_KEY][$purpose])) {
            $_SESSION[self::CSRF_KEY][$purpose] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CSRF_KEY][$purpose];
    }

    public function verifyCsrf(string $token, string $purpose = 'stop'): bool
    {
        $purpose = preg_replace('/[^a-z0-9_-]/', '', strtolower($purpose)) ?: 'default';
        $stored = (string) ($_SESSION[self::CSRF_KEY][$purpose] ?? '');

        return $stored !== '' && $token !== '' && hash_equals($stored, $token);
    }

    private function assertEligiblePair(int $actorUserId, int $subjectUserId): void
    {
        if ($actorUserId <= 0 || $subjectUserId <= 0 || $actorUserId === $subjectUserId) {
            throw new InvalidArgumentException('impersonation_pair_invalid');
        }

        $actor = \infoUser($actorUserId, 'id');
        $subject = \infoUser($subjectUserId, 'id');

        if (!($actor->exists ?? false) || ($actor->deleted ?? true) || !($actor->active ?? false)) {
            throw new InvalidArgumentException('impersonation_actor_invalid');
        }

        $actorAreas = is_array($actor->area ?? null) ? $actor->area : [];
        $actorAuthorities = is_array($actor->authority ?? null) ? $actor->authority : [];

        if (!in_array('backend', $actorAreas, true)
            || count(array_intersect($this->allowedActorAuthorities, $actorAuthorities)) === 0) {
            throw new InvalidArgumentException('impersonation_actor_not_authorized');
        }

        if (!($subject->exists ?? false) || ($subject->deleted ?? true) || !($subject->active ?? false)) {
            throw new InvalidArgumentException('impersonation_subject_invalid');
        }

        $subjectAreas = is_array($subject->area ?? null) ? $subject->area : [];
        if (!in_array('frontend', $subjectAreas, true) || in_array('backend', $subjectAreas, true)) {
            throw new InvalidArgumentException('impersonation_subject_not_frontend_only');
        }
    }

    /** Torna all'attore; se non è più attivo chiude anche la sua sessione. */
    private function restoreActor(object $current): bool
    {
        $actor = \infoUser($current->actor_user_id, 'id');
        $active = ($actor->exists ?? false) && !($actor->deleted ?? true) && ($actor->active ?? false);

        if ($active) {
            $_SESSION['user_id'] = $current->actor_user_id;
            unset($_SESSION[self::SESSION_KEY], $_SESSION[self::CSRF_KEY]);
        } else {
            unset($_SESSION[self::SESSION_KEY], $_SESSION[self::CSRF_KEY], $_SESSION['user_id']);
        }

        $this->regenerateSession();

        return $active;
    }

    private static function sessionFingerprint(): string
    {
        return hash('sha256', (string) session_id());
    }

    private function audit(string $event, ?int $actorUserId, ?int $subjectUserId, array $metadata = []): void
    {
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        \sqlInsert('auth_impersonation_audits', [
            'actor_user_id' => $actorUserId,
            'subject_user_id' => $subjectUserId,
            'event' => $event,
            'ip' => \Wonder\Http\ClientIp::resolve(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'metadata_json' => is_string($encoded) ? $encoded : '{}',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
