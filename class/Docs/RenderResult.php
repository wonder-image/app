<?php

namespace Wonder\Docs;

/** L'esito di `ExampleRunner::render()`: l'HTML, o l'errore, e il codice mostrato. */
final class RenderResult
{
    public function __construct(
        public readonly string $html,
        public readonly ?string $error,
        public readonly string $code,
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }
}
