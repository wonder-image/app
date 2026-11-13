<?php

namespace Wonder\App\ResourceSchema;

use Closure;

/**
 * Un pezzo di `formLayoutSchema()` che dipende dal record: la pagina del
 * form chiama `$build` con i valori e la modalità (`create`, `edit`, `form`)
 * e mette al suo posto quello che torna. `null` non mostra niente.
 *
 *     FromValues::make(static fn (array $values, string $mode) => $mode === 'edit'
 *         ? (new Card)->components([...])->columnSpan(3)
 *         : null)
 *
 * Fuori dalla pagina del form non stampa niente.
 */
final class FromValues
{
    private function __construct(
        private readonly Closure $build,
    ) {
    }

    public static function make(callable $build): self
    {
        return new self(Closure::fromCallable($build));
    }

    public function resolve(array $values, string $mode): ?object
    {
        $resolved = ($this->build)($values, $mode);

        return is_object($resolved) ? $resolved : null;
    }

    public function render(string $theme = ''): string
    {
        return '';
    }
}
