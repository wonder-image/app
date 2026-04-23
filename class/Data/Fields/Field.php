<?php

namespace Wonder\Data\Fields;

use Exception;
use Wonder\Concerns\HasSchema;
use Wonder\Data\Formatters\Formatter;
use Wonder\Data\Support\ValidationResult;
use Wonder\Data\Validators\RequiredValidator;
use Wonder\Data\Validators\Validator;

abstract class Field
{
    use HasSchema;

    public string $key;
    public string $type = 'text';

    public function __construct($key)
    {
        $this->key = $key;
    }

    public static function key(string $key): static
    {
        return new static($key);
    }

    public function sqlSchema(): array
    {
        return [];
    }

    public function defaultInputFormat(): array
    {
        return [];
    }

    public function config(): array
    {
        return [
            'sql' => $this->sqlSchema(),
            'input' => [
                'format' => $this->defaultInputFormat(),
            ],
        ];
    }

    public function required(): static
    {
        return $this->addValidator(new RequiredValidator());
    }

    public function isRequired(): bool
    {
        return $this->hasValidator(new RequiredValidator());
    }

    public function addFormatter(object $format): static
    {
        if (!is_subclass_of($format, Formatter::class)) {
            throw new Exception(
                'La classe '.$format::class.' non è un Formatter valido.'
            );
        }

        return $this->schemaPush('formatters', $format);
    }

    public function addValidator(object $validator): static
    {
        if (!is_subclass_of($validator, Validator::class)) {
            throw new Exception(
                'La classe '.$validator::class.' non è un Validator valido.'
            );
        }

        return $this->schemaPush('validators', $validator);
    }

    public function hasValidator(object $validator): bool
    {
        if (is_array($this->getSchema('validators'))) {
            foreach ($this->getSchema('validators') as $registeredValidator) {
                if ($registeredValidator::class === $validator::class) {
                    return true;
                }
            }
        }

        return false;
    }

    public function formatters(array $formatters): static
    {
        foreach ($formatters as $formatter) {
            $this->addFormatter($formatter);
        }

        return $this;
    }

    public function validators(array $validators): static
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }

        return $this;
    }

    public function unique(): static
    {
        return $this->schema('unique', true);
    }

    public function linkUnique(bool $enabled = true): static
    {
        return $this->schema('link_unique', $enabled);
    }

    public function sanitize(bool $sanitize = true): static
    {
        return $this->schema('sanitize', $sanitize);
    }

    public function json(bool $json = true): static
    {
        return $this->schema('json', $json);
    }

    public function htmlToText(bool $enabled = true): static
    {
        return $this->schema('html_to_text', $enabled);
    }

    public function fileToArray(bool $enabled = true): static
    {
        return $this->schema('file_to_array', $enabled);
    }

    public function file(bool $enabled = true): static
    {
        return $this->schema('file', $enabled);
    }

    public function extensions(array $extensions): static
    {
        return $this->schema('extensions', $extensions);
    }

    public function maxSize(int $maxSize): static
    {
        return $this->schema('max_size', $maxSize);
    }

    public function maxFile(int $maxFile): static
    {
        return $this->schema('max_file', $maxFile);
    }

    public function dir(string $dir): static
    {
        return $this->schema('dir', $dir);
    }

    public function reset(bool $reset = true): static
    {
        return $this->schema('reset', $reset);
    }

    public function resize(array $resize): static
    {
        return $this->schema('resize', $resize);
    }

    public function name(string $name): static
    {
        return $this->schema('name', $name);
    }

    public function webp(bool $webp = true): static
    {
        return $this->schema('webp', $webp);
    }

    public function validate($value, array $input = []): ValidationResult
    {
        if (is_array($this->getSchema('validators'))) {
            foreach ($this->getSchema('validators') as $validator) {
                $result = $validator->validate($value, $input);

                if (!$result->isValid()) {
                    return $result;
                }
            }
        }

        return ValidationResult::success($value);
    }

    public function format($value)
    {
        if (is_array($this->getSchema('formatters'))) {
            foreach ($this->getSchema('formatters') as $formatter) {
                $value = $formatter::format($value);
            }
        }

        return $value;
    }
}
