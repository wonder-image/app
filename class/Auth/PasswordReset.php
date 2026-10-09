<?php

namespace Wonder\Auth;

use InvalidArgumentException;
use RuntimeException;
use Wonder\Auth\Frontend\AccountEmail;
use Wonder\Sql\Transaction;

final class PasswordReset
{
    private OneTimeToken $tokens;

    public function __construct(int $ttlSeconds = 3600)
    {
        $this->tokens = new OneTimeToken('password_reset', $ttlSeconds);
    }

    /**
     * Con `$revokeOpenTokens` falso i link già mandati restano validi: per
     * esempio quello nell'email di un ordine precedente.
     */
    public function issueForUser(int $userId, ?string $continueUrl = null, array $metadata = [], bool $revokeOpenTokens = true): object
    {
        return $this->tokens->issue($userId, null, $continueUrl, $metadata, $revokeOpenTokens);
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

                // Il token è arrivato a quella casella: il possesso dell'email è provato.
                $verified = \markUserEmailVerified($consumed->subject_user_id, date('Y-m-d H:i:s'));
                if (!($verified->success ?? false)) {
                    throw new RuntimeException('password_reset_update_failed');
                }

                // Una sessione "ricordami" aperta prima del ripristino non resta valida.
                RememberMe::revokeUser($consumed->subject_user_id);
                // Con la vecchia password si può aver chiesto un cambio email: quel link non vale più.
                AccountEmail::revokeOpen($consumed->subject_user_id);

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
