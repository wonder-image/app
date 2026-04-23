<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\LowercaseFormatter;
use Wonder\Data\Formatters\String\SlugFormatter;
use Wonder\Data\Formatters\String\TitleCaseFormatter;
use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Formatters\String\UppercaseFormatter;
use Wonder\Data\Validators\StringValidator;

class Text extends Field
{
    public string $type = 'text';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->validators([
            new StringValidator(),
        ]);

        $this->formatters([
            new TrimFormatter(),
        ]);
    }

    public function lower(): self
    {
        return $this->addFormatter(new LowercaseFormatter());
    }

    public function upper(): self
    {
        return $this->addFormatter(new UppercaseFormatter());
    }

    public function ucwords(): self
    {
        return $this->addFormatter(new TitleCaseFormatter());
    }

    public function slug(): self
    {
        return $this->addFormatter(new SlugFormatter())
            ->sanitize(false)
            ->linkUnique()
            ->lower();
    }

    public function code(): self
    {
        return $this->sanitize(false)
            ->unique()
            ->lower();
    }

    public function codeUpper(): self
    {
        return $this->sanitize(false)
            ->linkUnique()
            ->upper();
    }

    public function sanitizeFirst(): static
    {
        return $this->lower()
            ->ucwords()
            ->sanitize();
    }
}
