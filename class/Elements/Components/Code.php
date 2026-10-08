<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;
use Wonder\Support\Code\Highlighter;

/**
 * Un blocco di codice con evidenziazione della sintassi e bottone "copia".
 *
 * Il codice è testo: viene escapato e colorato lato server dal
 * `Support\Code\Highlighter`, senza librerie JavaScript. Pensato per PHP
 * (documentazione, snippet di configurazione, comandi), accetta anche HTML,
 * CSS, JS, JSON, bash e testo semplice.
 */
class Code extends Component
{
    use CanSpanColumn, Renderer;

    private const SCHEMES = ['dark', 'light', 'auto'];

    public function __construct(string $code = '', string $language = 'php')
    {
        $this->code($code);
        $this->language($language);
        $this->copy();
        $this->scheme('dark');
    }

    public static function make(string $code = '', string $language = 'php'): static
    {
        return new static($code, $language);
    }

    /** Il codice da mostrare, così com'è: l'escape lo fa il renderer. */
    public function code(string $code): static
    {
        return $this->schema('code', $code);
    }

    /** `php`, `html`, `css`, `js`, `json`, `bash` o `text`; alias come `sh` e `javascript` sono accettati. */
    public function language(string $language): static
    {
        return $this->schema('language', Highlighter::normalizeLanguage($language));
    }

    /** L'etichetta nell'intestazione, per esempio il nome del file o "Terminal". */
    public function title(string $title): static
    {
        return $this->schema('title', $title);
    }

    /** Il bottone che copia il codice negli appunti; attivo di default. */
    public function copy(bool $copy = true): static
    {
        return $this->schema('copy', $copy);
    }

    /** Le etichette del bottone copia, prima e dopo il clic. */
    public function copyLabels(string $copy, string $copied): static
    {
        return $this
            ->schema('copy_label', $copy)
            ->schema('copied_label', $copied);
    }

    public function lineNumbers(bool $lineNumbers = true): static
    {
        return $this->schema('line_numbers', $lineNumbers);
    }

    /** Altezza massima del blocco (valore CSS): oltre, il codice scorre. */
    public function maxHeight(string $maxHeight): static
    {
        $maxHeight = trim($maxHeight);

        if ($maxHeight !== '' && !preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', $maxHeight)) {
            throw new InvalidArgumentException('Altezza massima non valida: usa un valore CSS come 320px o 20rem.');
        }

        return $this->schema('max_height', $maxHeight);
    }

    /**
     * `dark` (default, come un terminale), `light`, oppure `auto` per seguire
     * `data-bs-theme` della pagina.
     */
    public function scheme(string $scheme): static
    {
        $scheme = strtolower(trim($scheme));

        if (!in_array($scheme, self::SCHEMES, true)) {
            throw new InvalidArgumentException('Schema non valido. Valori ammessi: '.implode(', ', self::SCHEMES));
        }

        return $this->schema('scheme', $scheme);
    }

    public function getCode(): string
    {
        return (string) ($this->schema['code'] ?? '');
    }

    public function getLanguage(): string
    {
        return (string) ($this->schema['language'] ?? 'text');
    }
}
