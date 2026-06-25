<?php

namespace Wonder\Elements\Components;

/**
 * Sottoclasse di `Text` con `muted` di default e (per back-compat coi
 * call site esistenti che passano HTML grezzo) `html()` attivo. Quando
 * componi il testo a frammenti con `Text + Link`, preferisci
 * `Text::make()->muted()` esplicito invece di `HelpText`.
 */
class HelpText extends Text
{
    public function __construct(string $text = '')
    {
        parent::__construct($text);
        $this->muted();
        $this->html(true);
    }

    public static function make(string $text = ''): static
    {
        return new static($text);
    }
}
