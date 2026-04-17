<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\Elements\Concerns\CanSpanColumn;

class FormField
{
    use CanSpanColumn;

    public string $name;
    protected string $helper = 'text';

    private array $schema = [
        'label' => '',
        'attribute' => '',
        'value' => null,
        'options' => [],
        'version' => null,
        'multiple' => false,
        'file' => 'image',
        'uploader' => 'classic',
        'date_min' => null,
        'date_max' => null,
        'error' => '',
    ];

    public function __construct(
        string $name,
        string $helper = 'text',
    ) {
        $this->name = trim($name);
        $this->helper = trim($helper) !== '' ? trim($helper) : 'text';
    }

    public static function key(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): self
    {
        $this->schema['label'] = $label;

        return $this;
    }

    public function attribute(string $attribute): self
    {
        $attribute = trim($attribute);

        if ($attribute === '') {
            return $this;
        }

        $current = trim((string) ($this->schema['attribute'] ?? ''));
        $this->schema['attribute'] = trim($current.' '.$attribute);

        return $this;
    }

    public function required(bool $required = true): self
    {
        return $required ? $this->attribute('required') : $this;
    }

    public function disabled(bool $disabled = true): self
    {
        return $disabled ? $this->attribute('disabled') : $this;
    }

    public function readonly(bool $readonly = true): self
    {
        return $readonly ? $this->attribute('readonly') : $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->schema['multiple'] = $multiple;

        return $multiple ? $this->attribute('multiple') : $this;
    }

    public function value(mixed $value): self
    {
        $this->schema['value'] = $value;

        return $this;
    }

    public function options(array $options): self
    {
        $this->schema['options'] = $options;

        return $this;
    }

    public function version(?string $version): self
    {
        $this->schema['version'] = $version;

        return $this;
    }

    public function old(): self
    {
        return $this->version('old');
    }

    public function file(string $file): self
    {
        $this->schema['file'] = trim($file);

        return $this;
    }

    public function uploader(string $uploader = 'classic'): self
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }

    public function dateMin(?string $dateMin): self
    {
        $this->schema['date_min'] = $dateMin;

        return $this;
    }

    public function dateMax(?string $dateMax): self
    {
        $this->schema['date_max'] = $dateMax;

        return $this;
    }

    public function error(string $error): self
    {
        $this->schema['error'] = $error;

        return $this;
    }

    public function text(): self
    {
        $this->helper = 'text';

        return $this;
    }

    public function textGenerator(): self
    {
        $this->helper = 'textGenerator';

        return $this;
    }

    public function textDate(): self
    {
        $this->helper = 'textDate';

        return $this;
    }

    public function textDatetime(): self
    {
        $this->helper = 'textDatetime';

        return $this;
    }

    public function dateInput(?string $dateMin = null, ?string $dateMax = null): self
    {
        $this->helper = 'dateInput';
        $this->dateMin($dateMin);
        $this->dateMax($dateMax);

        return $this;
    }

    public function dateRange(?string $dateMin = null, ?string $dateMax = null): self
    {
        $this->helper = 'dateRange';
        $this->dateMin($dateMin);
        $this->dateMax($dateMax);

        return $this;
    }

    public function color(): self
    {
        $this->helper = 'color';

        return $this;
    }

    public function email(): self
    {
        $this->helper = 'email';

        return $this;
    }

    public function number(): self
    {
        $this->helper = 'number';

        return $this;
    }

    public function price(): self
    {
        $this->helper = 'price';

        return $this;
    }

    public function percentige(): self
    {
        $this->helper = 'percentige';

        return $this;
    }

    public function password(): self
    {
        $this->helper = 'password';

        return $this;
    }

    public function tel(): self
    {
        $this->helper = 'phone';

        return $this;
    }

    public function phone(): self
    {
        return $this->tel();
    }

    public function url(): self
    {
        $this->helper = 'url';

        return $this;
    }

    public function textarea(?string $version = null): self
    {
        $this->helper = 'textarea';
        $this->version($version);

        return $this;
    }

    public function select(array $options = [], ?string $version = null): self
    {
        $this->helper = 'select';
        $this->options($options);
        $this->version($version);

        return $this;
    }

    public function selectSearch(array $options = [], bool $multiple = false, ?string $version = null): self
    {
        $this->helper = 'selectSearch';
        $this->options($options);
        $this->multiple($multiple);
        $this->version($version);

        return $this;
    }

    public function checkbox(): self
    {
        $this->helper = 'checkbox';

        return $this;
    }

    public function inputFile(string $file = 'image'): self
    {
        $this->helper = 'inputFile';
        $this->file($file);

        return $this;
    }

    public function inputFileDragDrop(string $file = 'image', string $uploader = 'classic'): self
    {
        $this->helper = 'inputFileDragDrop';
        $this->file($file);
        $this->uploader($uploader);

        return $this;
    }

    public function render(?string $theme = null): string
    {
        $this->ensureHelperLoaded();

        if (!function_exists($this->helper)) {
            throw new RuntimeException("Helper form backend non disponibile: {$this->helper}()");
        }

        $label = (string) ($this->schema['label'] ?? $this->name);
        $attribute = trim((string) ($this->schema['attribute'] ?? '')) ?: null;
        $value = $this->schema['value'] ?? null;
        $version = $this->schema['version'] ?? null;
        $options = (array) ($this->schema['options'] ?? []);
        $file = (string) ($this->schema['file'] ?? 'image');
        $uploader = (string) ($this->schema['uploader'] ?? 'classic');
        $multiple = (bool) ($this->schema['multiple'] ?? false);
        $dateMin = $this->schema['date_min'] ?? null;
        $dateMax = $this->schema['date_max'] ?? null;

        return match ($this->helper) {
            'select' => select($label, $this->name, $options, $version, $attribute, $value),
            'selectSearch' => selectSearch($label, $this->name, $options, $multiple, $version, $attribute, $value),
            'textarea' => textarea($label, $this->name, $attribute, $version, $value),
            'dateInput' => dateInput($label, $this->name, $dateMin, $dateMax, $attribute, $value),
            'dateRange' => dateRange($label, $this->name, $dateMin, $dateMax, $attribute, $value),
            'inputFile' => inputFile($label, $this->name, $file, $attribute, $value),
            'inputFileDragDrop' => inputFileDragDrop($label, $this->name, $uploader, $file, $attribute, $value),
            default => call_user_func($this->helper, $label, $this->name, $attribute, $value),
        };
    }

    private function ensureHelperLoaded(): void
    {
        if (function_exists($this->helper)) {
            return;
        }

        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');
        $inputPath = $rootApp !== '' ? $rootApp.'/function/backend/input.php' : '';

        if ($inputPath !== '' && file_exists($inputPath)) {
            require_once $inputPath;
        }
    }
}
