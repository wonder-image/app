<?php

namespace Wonder\Elements\Components;

class InfoCard extends AbstractValueCard
{
    public function __construct(
        string $title = '',
        string|int|float|bool|null $value = null
    ) {
        parent::__construct($title, $value);

        $this->valueLevel(5);
    }
}
