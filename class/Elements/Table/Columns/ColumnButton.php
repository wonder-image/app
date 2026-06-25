<?php

namespace Wonder\Elements\Table\Columns;

use Wonder\Elements\Table\Column;

class ColumnButton extends Column
{
    public string $type = 'button';

    public function __construct(string $name = 'menu')
    {
        parent::__construct($name);
        $this->setType('button');
    }

    public function action(string $action, bool $enabled = true): self
    {
        $action = trim($action);

        if ($action === '') {
            return $this;
        }

        $actions = (array) ($this->schema['actions'] ?? []);

        if ($enabled) {
            $actions[$action] = true;
        } else {
            unset($actions[$action]);
        }

        return $this->schema('actions', $actions);
    }

    public function actions(array $actions): self
    {
        foreach ($actions as $action => $enabled) {
            if (is_int($action) && is_string($enabled)) {
                $this->action($enabled, true);
                continue;
            }

            if (is_string($action)) {
                $this->action($action, (bool) $enabled);
            }
        }

        return $this;
    }
}
