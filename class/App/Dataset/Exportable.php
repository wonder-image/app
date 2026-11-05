<?php

namespace Wonder\App\Dataset;

/** Una fonte da cui si esportano righe. */
interface Exportable extends Source
{
    /**
     * Le righe da esportare, già filtrate per chi le chiede.
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(): iterable;
}
