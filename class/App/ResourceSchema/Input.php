<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Support\FormFieldElementFactory;
use Wonder\Elements\Concerns\CanSpanColumn;

/**
 * Base condivisa fra `FormField` (facade storica con i 45 type-helper) e le
 * classi di tipo dedicate sotto `Wonder\App\ResourceSchema\Inputs\` (nuovo
 * pattern, mirror di `Wonder\Data\Fields\*`).
 *
 * Tutta la "macchina" dello schema (label/attribute/value/prepare/context/
 * render/...) vive qui. Le sottoclassi aggiungono solo i setters specifici
 * del proprio tipo (es. `InputPassword::minLength()`, `InputFile::extensions()`).
 *
 * Migrazione (vedi piano):
 *   1. `Input` + `InputText` + `InputPassword` ← questa PR (foundation).
 *   2. `InputFile` + `InputAcceptDocument`.
 *   3. Choice family (Select/Radio/Checkbox/CheckTree/...).
 *   4. Date/Time/DateRange.
 *   5. Repeater, GoogleAddress, ReCAPTCHA, ecc.
 *   6. Rimozione dei type-helper da `FormField` (resta come dispatcher).
 *
 * Il `FormFieldElementFactory` lavora già contro questa base (type hint
 * `Input`), così sia `FormField` legacy sia le nuove `Input*` sono accettate
 * indifferentemente al render.
 */
abstract class Input
{
    use CanSpanColumn;

    public string $name;
    protected string $helper = 'text';

    /** @var array<string, mixed> */
    protected array $schema = [
        'label' => '',
        'attribute' => '',
        'value' => null,
        'options' => [],
        'search_bar' => false,
        'version' => null,
        'multiple' => false,
        'file' => 'image',
        'uploader' => 'classic',
        'date_min' => null,
        'date_max' => null,
        'time_step' => null,
        'error' => '',
        'prepare' => [],
        'context' => [],
    ];

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }

    public static function key(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->schema['label'] = $label;

        return $this;
    }

    public function attribute(string $attribute): static
    {
        $attribute = trim($attribute);

        if ($attribute === '') {
            return $this;
        }

        $current = trim((string) ($this->schema['attribute'] ?? ''));
        $this->schema['attribute'] = trim($current.' '.$attribute);

        return $this;
    }

    public function required(bool $required = true): static
    {
        return $required ? $this->attribute('required') : $this;
    }

    public function disabled(bool $disabled = true): static
    {
        return $disabled ? $this->attribute('disabled') : $this;
    }

    public function readonly(bool $readonly = true): static
    {
        return $readonly ? $this->attribute('readonly') : $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->schema['multiple'] = $multiple;

        return $multiple ? $this->attribute('multiple') : $this;
    }

    public function value(mixed $value): static
    {
        $this->schema['value'] = $value;

        return $this;
    }

    public function inputName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function options(array $options): static
    {
        $this->schema['options'] = $options;

        return $this;
    }

    public function searchBar(bool $searchBar = true): static
    {
        $this->schema['search_bar'] = $searchBar;

        return $this;
    }

    public function version(?string $version): static
    {
        $this->schema['version'] = $version;

        return $this;
    }

    public function old(): static
    {
        return $this->version('old');
    }

    public function uploader(string $uploader = 'classic'): static
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }

    public function dateMin(?string $dateMin): static
    {
        $this->schema['date_min'] = $dateMin;

        return $this;
    }

    public function dateMax(?string $dateMax): static
    {
        $this->schema['date_max'] = $dateMax;

        return $this;
    }

    public function timeStep(?int $timeStep): static
    {
        $this->schema['time_step'] = $timeStep;

        return $this;
    }

    public function error(string $error): static
    {
        $this->schema['error'] = $error;

        return $this;
    }

    public function prepare(string|array $key, mixed $value = true): static
    {
        if (is_array($key)) {
            $this->schema['prepare'] = array_merge(
                (array) ($this->schema['prepare'] ?? []),
                $key
            );

            return $this;
        }

        $this->schema['prepare'][trim($key)] = $value;

        return $this;
    }

    public function context(string|array $key, mixed $value = true): static
    {
        if (is_array($key)) {
            $this->schema['context'] = array_merge(
                (array) ($this->schema['context'] ?? []),
                $key
            );

            return $this;
        }

        $this->schema['context'][trim($key)] = $value;

        return $this;
    }

    public function storeAs(string $name): static
    {
        return $this->prepare('name', $name);
    }

    public function maxSize(int $size): static
    {
        return $this->prepare('max_size', $size);
    }

    public function maxFile(int $count): static
    {
        return $this->prepare('max_file', $count);
    }

    /**
     * Estensioni accettate per l'upload (post-server validation).
     * Accetta sia un array (`['png', 'jpg']`) sia una stringa
     * separata da virgole/spazi/pipe (`'png,jpg'`, `'png jpg'`,
     * `'.png|.jpg'`). Le estensioni vengono normalizzate a
     * lowercase senza punto iniziale.
     */
    public function extensions(string|array $extensions): static
    {
        if (is_string($extensions)) {
            $extensions = preg_split('/[\s,|]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $normalized = [];

        foreach ($extensions as $ext) {
            $value = ltrim(strtolower(trim((string) $ext)), '.');

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $this->prepare('extensions', array_values(array_unique($normalized)));
    }

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->schema;
        }

        if ($key === 'helper') {
            return $this->helper;
        }

        return $this->schema[$key] ?? null;
    }

    /**
     * Renderizza il campo come HTML del tema attivo (o di quello esplicito).
     *
     * Unica strada: `FormFieldElementFactory::make()` mappa il `helper`
     * a un Element neutro, che il `Wonder\Themes\Resolver` rende col
     * tema corrente. Se l'helper non è gestito dalla Factory si lancia
     * un'eccezione esplicita — niente fallback a funzioni procedurali.
     */
    public function render(?string $theme = null): string
    {
        $rendered = FormFieldElementFactory::render($this, $theme);

        if ($rendered === null) {
            throw new RuntimeException(
                "Helper form non supportato: {$this->helper}. "
                ."Aggiungi la mappatura in Wonder\\App\\Support\\FormFieldElementFactory::make()."
            );
        }

        return $rendered;
    }

    /**
     * Permette di usare il campo come stringa (`echo`, concatenazione,
     * interpolazione) senza dover chiamare esplicitamente `->render()`.
     * Utile soprattutto per `*Resource::getInput('foo')` dentro le view.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
