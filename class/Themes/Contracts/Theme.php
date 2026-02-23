<?php

namespace Wonder\Themes\Contracts;

interface Theme
{
    /**
     * Chiave univoca del tema (es. "bootstrap", "wonder").
     */
    public function key(): string;

    /**
     * Segmento namespace usato dai renderer (es. "Bootstrap", "Wonder").
     */
    public function namespace(): string;

    /**
     * Tema fallback opzionale se il renderer non esiste nel tema corrente.
     */
    public function fallback(): ?string;
}
