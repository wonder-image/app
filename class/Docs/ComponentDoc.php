<?php

namespace Wonder\Docs;

use InvalidArgumentException;

/**
 * La scheda di documentazione di un componente: la classe Element, come si
 * presenta nel catalogo (categoria, gruppo, ordine), la descrizione, gli
 * esempi e le note per tema.
 *
 * Ogni file sotto `docs/components/<categoria>/` ritorna una di queste
 * istanze; `Catalog` le raccoglie. La disponibilità nei temi non si dichiara:
 * la calcola `ThemeSupport` chiedendo al `Resolver` se esiste il renderer.
 * `unsupported()` serve solo quando il renderer esiste ma non rende davvero
 * il componente (una `Modal` senza `frontend()` nel tema Wonder).
 */
final class ComponentDoc
{
    private string $slug;
    private string $title;
    private string $description = '';
    private string $category = '';
    private string $group = '';
    private int $order = 100;
    /** @var string[] */
    private array $tags = [];
    /** @var string[] */
    private array $uses = [];
    /** @var Example[] */
    private array $examples = [];
    /** @var array<string, string> */
    private array $notes = [];
    /** @var array<string, string> */
    private array $unsupported = [];
    /** @var array<int, array{href: string, label: string}> */
    private array $docs = [];
    /** @var string[] */
    private array $related = [];
    private ?string $deprecated = null;
    private ?string $file = null;

    private function __construct(
        private readonly string $class,
    ) {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Classe {$class} non trovata: la scheda deve puntare a un Element esistente.");
        }

        $this->title = $this->shortName();
        $this->slug = self::slugify($this->shortName());
        $this->uses = [$class];
    }

    public static function for(string $class): self
    {
        return new self(ltrim($class, '\\'));
    }

    public function slug(string $slug): self
    {
        $slug = strtolower(trim($slug));

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException("Slug {$slug} non valido: lettere minuscole, numeri e trattini.");
        }

        $this->slug = $slug;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = trim($title) !== '' ? trim($title) : $this->shortName();

        return $this;
    }

    /** Testo semplice; le parti fra apici inversi diventano `<code>`. */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function category(string $category): self
    {
        $this->category = strtolower(trim($category));

        return $this;
    }

    public function group(string $group): self
    {
        $this->group = strtolower(trim($group));

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function tags(string ...$tags): self
    {
        $this->tags = array_values(array_unique(array_merge(
            $this->tags,
            array_map(static fn (string $tag): string => trim($tag), $tags)
        )));

        return $this;
    }

    /**
     * Le classi che gli esempi usano: per ognuna, se lo snippet nomina la
     * classe senza importarla, la riga `use` viene aggiunta in testa al
     * codice mostrato ed eseguito. La classe del componente c'è già.
     */
    public function uses(string ...$classes): self
    {
        foreach ($classes as $class) {
            $class = ltrim(trim($class), '\\');

            if ($class !== '' && !in_array($class, $this->uses, true)) {
                $this->uses[] = $class;
            }
        }

        return $this;
    }

    /**
     * Aggiunge un esempio: `example('Titolo', $codice, 'descrizione')` per i
     * casi semplici, oppure un `Example` già configurato.
     */
    public function example(Example|string $example, ?string $code = null, ?string $description = null): self
    {
        if (is_string($example)) {
            $example = Example::make($example)->code((string) $code);

            if ($description !== null) {
                $example->description($description);
            }
        }

        $this->examples[] = $example;

        return $this;
    }

    /** Una nota su come il componente si comporta in un tema. */
    public function note(string $theme, string $text): self
    {
        $this->notes[strtolower(trim($theme))] = $text;

        return $this;
    }

    /**
     * Dichiara un tema come non supportato anche se un renderer esiste,
     * con il motivo mostrato nel catalogo.
     */
    public function unsupported(string $theme, string $reason): self
    {
        $this->unsupported[strtolower(trim($theme))] = $reason;

        return $this;
    }

    /** Un rimando alla guida: un percorso relativo a `docs/app/` o un URL. */
    public function docs(string $href, ?string $label = null): self
    {
        $this->docs[] = ['href' => trim($href), 'label' => trim((string) ($label ?? $href))];

        return $this;
    }

    public function related(string ...$slugs): self
    {
        $this->related = array_values(array_unique(array_merge(
            $this->related,
            array_map(static fn (string $slug): string => strtolower(trim($slug)), $slugs)
        )));

        return $this;
    }

    public function deprecated(string $reason): self
    {
        $this->deprecated = $reason;

        return $this;
    }

    /** @internal usato da Catalog per ricordare il file di origine */
    public function file(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function shortName(): string
    {
        $position = strrpos($this->class, '\\');

        return $position === false ? $this->class : substr($this->class, $position + 1);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    /** @return string[] */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @return string[] */
    public function getUses(): array
    {
        return $this->uses;
    }

    /** @return Example[] */
    public function getExamples(): array
    {
        return $this->examples;
    }

    public function getExample(int $index): ?Example
    {
        return $this->examples[$index] ?? null;
    }

    /** @return array<string, string> */
    public function getNotes(): array
    {
        return $this->notes;
    }

    public function getNote(string $theme): string
    {
        return $this->notes[strtolower(trim($theme))] ?? '';
    }

    /** @return array<string, string> */
    public function getUnsupported(): array
    {
        return $this->unsupported;
    }

    /** @return array<int, array{href: string, label: string}> */
    public function getDocs(): array
    {
        return $this->docs;
    }

    /** @return string[] */
    public function getRelated(): array
    {
        return $this->related;
    }

    public function getDeprecated(): ?string
    {
        return $this->deprecated;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public static function slugify(string $name): string
    {
        $slug = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $name);
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $slug));

        return trim($slug, '-');
    }
}
