<?php

namespace Wonder\Http\Exceptions;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct(404, $message);
    }
}
