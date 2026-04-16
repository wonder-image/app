<?php
    
namespace Wonder\Elements\Table\Columns;

use Wonder\Elements\Table\Column;

class ColumnText extends Column
{
    public string $type = 'text';

    public function date(bool $date = true): self
    {
        return $this->setType($date ? 'date' : 'text');
    }

    public function phone(bool $phone = true): self
    {
        return $this->setType($phone ? 'phone' : 'text');
    }

    public function price(bool $price = true): self
    {
        return $this->setType($price ? 'price' : 'text');
    }
}
