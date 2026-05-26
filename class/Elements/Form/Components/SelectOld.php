<?php

namespace Wonder\Elements\Form\Components;

/**
 * Variante "legacy" del Select per il frontend: renderizza un
 * `<select>` HTML nativo senza il wrap `d-none` + JS di styling
 * custom usato dal Select standard di Wonder. Mantenuto per backward
 * compatibility con form che non caricano lo script di abbellimento.
 */
class SelectOld extends Select
{
}
