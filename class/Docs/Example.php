<?php

namespace Wonder\Docs;

use InvalidArgumentException;

/**
 * Un esempio d'uso di un componente: il codice PHP mostrato nella pagina è lo
 * stesso che viene eseguito per l'anteprima, così i due non possono divergere.
 *
 * Lo snippet è PHP normale, senza `<?php`: una o più istruzioni, l'ultima è
 * l'espressione che produce l'Element (o un array di Element, o una stringa
 * HTML). Non serve `return`: lo aggiunge `Snippet::executable()`. Un `echo`
 * esplicito funziona lo stesso, l'output viene catturato.
 */
final class Example
{
    private string $code = '';
    private string $description = '';
    /** @var string[] */
    private array $themes = [];
    private int $height = 0;

    private function __construct(
        private readonly string $title,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Un esempio vuole un titolo.');
        }
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function code(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Limita l'esempio ad alcuni temi, quando usa un'API che esiste solo lì
     * (per esempio `Accordion::link()` in Bootstrap o `Modal::frontend()` in
     * Wonder). Senza, l'esempio vale per ogni tema in cui il componente ha
     * un renderer.
     */
    public function themes(string ...$themes): self
    {
        $this->themes = array_values(array_unique(array_map(
            static fn (string $theme): string => strtolower(trim($theme)),
            $themes
        )));

        return $this;
    }

    /** Altezza minima dell'anteprima in pixel, per contenuti che si aprono (dropdown, modal). */
    public function height(int $height): self
    {
        $this->height = max(0, $height);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return string[] */
    public function getThemes(): array
    {
        return $this->themes;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function supports(string $theme): bool
    {
        return $this->themes === [] || in_array(strtolower(trim($theme)), $this->themes, true);
    }
}
