<?php

namespace Wonder\Elements\Components;

/**
 * Variante di `Text` che parte già con il "raw HTML" attivo.
 *
 * Comodo per i casi in cui hai già una stringa con markup (es. un
 * frammento i18n con `<a>`, `<b>`, ecc.) e non vuoi pensare a
 * `->html()` ogni volta. Per i casi semplici (testo + un link)
 * preferisci comunque `Text + Link` a frammenti — produce HTML più
 * sicuro e più chiaro da modificare.
 *
 *   RichText::make('Vedi la <b>documentazione</b> <a href="/docs">qui</a>.')
 */
class RichText extends Text
{
    public function __construct(string $html = '')
    {
        parent::__construct($html);
        $this->html(true);
    }

    public static function make(string $text = ''): static
    {
        return new static($text);
    }
}
