<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Support\FormFieldElementFactory;
use Wonder\Elements\Concerns\CanSpanColumn;

/**
 * Base condivisa fra `FormField` (facade retro-compatibile con i 45
 * type-helper) e le classi di tipo dedicate sotto
 * `Wonder\App\ResourceSchema\Inputs\` (mirror di `Wonder\Data\Fields\*`).
 *
 * Qui vive **solo l'API universale**, quella che ha senso su qualunque input:
 * `label`, `value`, `required`, `disabled`, `readonly`, `autocomplete`,
 * `attribute`, `visibleWhen`, `hiddenWhen`, `error`, `inputName`, `storeAs`,
 * `prepare`, `context`, `columnSpan`, `get`, `render`, `__toString`.
 *
 * I modificatori specifici di un tipo (`options()`, `decimals()`,
 * `minLength()`, `maxFile()`, ...) NON stanno qui: vivono sulla classe del
 * proprio tipo, così `FormField::key('x')->number()` espone in autocomplete
 * i soli setters numerici e non, per dire, `options()` o `requireUppercase()`.
 *
 * Il passaggio dal generico al tipizzato avviene in {@see morphInto()}: i
 * type-helper di `FormField` non ritornano più `self` ma l'istanza `Input*`
 * corrispondente, trasferendo nome e schema già accumulati.
 *
 * Il `FormFieldElementFactory` lavora contro questa base (type hint `Input`),
 * quindi accetta indifferentemente la facade e le classi tipizzate: il
 * contratto verso i temi resta `helper` + `schema`.
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
        'autocomplete' => null,
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

    /**
     * Converte l'istanza corrente nella classe di input tipizzata `$inputClass`,
     * trasferendo nome, schema accumulato e column span.
     *
     * È il meccanismo che rende i type-helper di `FormField` retro-compatibili
     * pur cambiando tipo di ritorno: `FormField::key('p')->label('Prezzo')
     * ->number()` continua a funzionare — la `label()` chiamata *prima* del
     * type-helper viaggia nello schema e sopravvive al morph — ma da
     * `->number()` in poi l'oggetto è un `InputNumber` e l'autocomplete mostra
     * solo i suoi setters.
     *
     * L'`helper` NON viene copiato: è la costante della classe di destinazione
     * a definirlo (`InputNumber::$helper = 'number'`), che è esattamente ciò
     * che il type-helper sta scegliendo.
     *
     * @template T of Input
     * @param class-string<T> $inputClass
     * @return T
     */
    protected function morphInto(string $inputClass): Input
    {
        $input = new $inputClass($this->name);

        $input->schema = $this->schema;

        if ($this->hasExplicitColumnSpan()) {
            $input->columnSpan($this->columnSpan);
        }

        return $input;
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

    /**
     * Visibilità condizionale: mostra questo campo solo quando il campo di
     * riferimento `$field` assume uno dei valori dati (altrimenti lo nasconde).
     *
     * Il toggle avviene lato client (JS backend di wonder-image/lib) leggendo i
     * data-attribute qui aggiunti, quindi funziona con qualsiasi tipo di input
     * senza modifiche ai renderer dei temi.
     *
     * @param string|array<int, string> $values
     */
    public function visibleWhen(string $field, string|array $values): static
    {
        return $this->conditionalVisibility('visible', $field, $values);
    }

    /**
     * Visibilità condizionale inversa: nasconde questo campo quando il campo di
     * riferimento `$field` assume uno dei valori dati.
     *
     * @param string|array<int, string> $values
     */
    public function hiddenWhen(string $field, string|array $values): static
    {
        return $this->conditionalVisibility('hidden', $field, $values);
    }

    /**
     * @param string|array<int, string> $values
     */
    private function conditionalVisibility(string $mode, string $field, string|array $values): static
    {
        $field = trim($field);

        if ($field === '') {
            return $this;
        }

        $values = implode(',', array_map(
            static fn ($value): string => trim((string) $value),
            is_array($values) ? $values : [$values]
        ));

        return $this->attribute(sprintf(
            'data-%1$s-when="%2$s" data-%1$s-when-values="%3$s"',
            $mode,
            htmlspecialchars($field, ENT_QUOTES),
            htmlspecialchars($values, ENT_QUOTES)
        ));
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

    public function autocomplete(bool|string $autocomplete = true): static
    {
        if (is_string($autocomplete)) {
            $autocomplete = trim($autocomplete);
            $this->schema['autocomplete'] = $autocomplete !== '' ? $autocomplete : null;

            return $this;
        }

        $this->schema['autocomplete'] = $autocomplete;

        return $this;
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
