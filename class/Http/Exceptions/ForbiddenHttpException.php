<?php

namespace Wonder\Http\Exceptions;

final class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Accesso negato.')
    {
        parent::__construct(403, $message);
    }
}
