<?php

namespace Wonder\Data\Fields;

class Json extends Field
{
    public string $type = 'json';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->json();
        $this->sanitize(false);
    }

    public function sqlSchema(): array
    {
        return [
            'type' => 'JSON',
        ];
    }

    public function defaultInputFormat(): array
    {
        return [
            'sanitize' => false,
            'json' => true,
        ];
    }
}
