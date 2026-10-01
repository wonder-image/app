<?php

namespace Wonder\Auth;

use InvalidArgumentException;
use RuntimeException;
use Wonder\Sql\Transaction;

final class PasswordReset
{
    private OneTimeToken $tokens;

    public function __construct(int $ttlSeconds = 3600)
    {
        $this->tokens = new OneTimeToken('password_reset', $ttlSeconds);
    }

    public function issueForUser(int $userId, ?string $continueUrl = null, array $metadata = []): object
    {
        return $this->tokens->issue($userId, null, $continueUrl, $metadata);
    }

    public function reset(string $token, string $plainPassword): object
    {
        if (trim($plainPassword) === '') {
            throw new InvalidArgumentException('password_reset_password_missing');
        }

        try {
            return Transaction::run(function () use ($token, $plainPassword): object {
                $consumed = $this->tokens->consume($token);

                if ($consumed === null || $consumed->subject_user_id <= 0) {
                    return (object) ['success' => false, 'reason' => 'password_reset_token_invalid'];
                }

                $update = \sqlModify('user', [
                    'password' => \hashPassword($plainPassword),
                ], 'id', $consumed->subject_user_id);

                if (!($update->success ?? false)) {
                    throw new RuntimeException('password_reset_update_failed');
                }

                return (object) [
                    'success' => true,
                    'user_id' => $consumed->subject_user_id,
                    'continue_url' => $consumed->continue_url,
                ];
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'password_reset_update_failed') {
                return (object) ['success' => false, 'reason' => 'password_reset_update_failed'];
            }

            throw $exception;
        }
    }
}
