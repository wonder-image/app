<?php

namespace Wonder\Http\Exceptions;

final class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Accesso richiesto.')
    {
        parent::__construct(401, $message);
    }
}
