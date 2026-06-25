<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasLinkAttributes;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

/**
 * Testo componibile. Tre modalità d'uso:
 *
 *   // 1. Testo semplice (auto-escape):
 *   Text::make('Ciao Mondo')
 *
 *   // 2. Testo + link inline, senza HTML grezzo:
 *   Text::make('Vedi la ')
 *       ->link('/terms', 'Termini', ['blank' => true])
 *       ->append(' e la ')
 *       ->link('/privacy', 'Privacy Policy', ['blank' => true])
 *
 *   // 3. HTML grezzo (per i casi i18n con markup già inline):
 *   Text::make('Acconsento alla <b>Privacy Policy</b>.')->html()
 *   // oppure: RichText::make('...')
 *
 * Internamente lo schema mantiene un array `parts` di frammenti
 * `string|Link` che il renderer compone in ordine.
 *
 * @method static muted(bool $muted = true)
 * @method static small(bool $small = true)
 * @method static bold(bool $bold = true)
 * @method static italic(bool $italic = true)
 * @method static color(string $color)
 * @method static align(string $align)
 */
class Text extends Component
{
    use CanSpanColumn, HasLinkAttributes, HasText, Renderer {
        href as private;
        getHref as private;
        target as private;
        blank as private;
        external as private;
        rel as private;
        title as private;
        onclick as private;
        download as private;
    }

    public function __construct(string $text = '')
    {
        $this->text = $text;

        if ($text !== '') {
            $this->schema('parts', [$text]);
        } else {
            $this->schema('parts', []);
        }
    }

    public static function make(string $text = ''): static
    {
        return new static($text);
    }

    public function text(string $text): static
    {
        $this->text = $text;
        $this->schema('parts', $text === '' ? [] : [$text]);

        return $this;
    }

    /**
     * Aggiunge un frammento in coda. Accetta una stringa (escapata in
     * modalità testo, raw in modalità html) o un `Link`. Mantiene anche
     * `getText()` allineato (concatenazione plain) per chi consuma
     * l'API legacy.
     */
    public function append(string|Link $part): static
    {
        $parts = (array) ($this->schema['parts'] ?? []);
        $parts[] = $part;
        $this->schema('parts', $parts);

        if (is_string($part)) {
            $this->text .= $part;
        } else {
            $this->text .= $part->getLabel();
        }

        return $this;
    }

    /**
     * Imposta l'intera lista di frammenti in una volta sola. Sovrascrive
     * `parts` esistenti. Utile quando componi il testo da una collection.
     *
     * @param array<int, string|Link> $parts
     */
    public function parts(array $parts): static
    {
        $clean = [];
        $textBuffer = '';

        foreach ($parts as $part) {
            if (is_string($part)) {
                $clean[] = $part;
                $textBuffer .= $part;
            } elseif ($part instanceof Link) {
                $clean[] = $part;
                $textBuffer .= $part->getLabel();
            }
        }

        $this->text = $textBuffer;

        return $this->schema('parts', $clean);
    }

    /**
     * Shortcut per `->append(Link::to($href, $label))`. Le opzioni di
     * `$options` vengono inoltrate al concern link condiviso:
     * `blank`, `target`, `rel`, `title`, `onclick`, `download`,
     * `icon`, `class`, `muted`, `attributes`.
     *
     * @param array<string, mixed> $options
     */
    public function link(string $href, string $label, array $options = []): static
    {
        return $this->append(
            $this->applyLinkOptions(Link::to($href, $label), $options)
        );
    }

    /**
     * Disattiva l'escape sul singolo frammento string (il `Link` viene
     * comunque montato con attributi escapati). Usalo solo quando hai
     * già una stringa HTML "fidata" — altrimenti preferisci comporre
     * via `append(Link::to(...))`.
     */
    public function html(bool $html = true): static
    {
        return $this->schema('html', $html);
    }

    public function tag(string $tag): static
    {
        return $this->schema('tag', strtolower(trim($tag)));
    }

    public function lead(bool $lead = true): static
    {
        return $this->schema('lead', $lead);
    }
}
