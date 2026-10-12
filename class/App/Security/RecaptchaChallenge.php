<?php

namespace Wonder\App\Security;

use RuntimeException;
use Throwable;

/** Punteggio v3 basso con la chiave a casella configurata: il form deve mostrare la casella. */
final class RecaptchaChallenge extends RuntimeException
{
    public const CHALLENGE_CODE = 618;

    public function __construct(?Throwable $previous = null)
    {
        $message = function_exists('__t') ? trim((string) __t('notifications.'.self::CHALLENGE_CODE.'.text')) : '';

        parent::__construct($message !== '' ? $message : 'Conferma di non essere un robot', self::CHALLENGE_CODE, $previous);
    }
}
