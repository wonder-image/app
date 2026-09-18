<?php

namespace Wonder\Backend\Contracts;

/**
 * Riquadro della home del backend, dichiarato da un modulo nella propria
 * configurazione (`backend.home_widgets`).
 */
interface HomeWidget
{
    public function title(): string;

    /** Markup del riquadro; deve bastare a sé stesso. */
    public function render(): string;

    /**
     * Ruoli che lo vedono; vuoto: tutti.
     *
     * @return list<string>
     */
    public function authorities(): array;

    /** Ordine crescente. */
    public function order(): int;
}
