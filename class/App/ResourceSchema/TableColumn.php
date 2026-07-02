<?php

namespace Wonder\App\ResourceSchema;

use Wonder\Elements\Table\Column;

final class TableColumn extends Column
{
    public static function key(string $name): self
    {
        return new self($name);
    }

    public function text(): self
    {
        return $this->setType('text');
    }

    public function date(): self
    {
        return $this->setType('date');
    }

    public function datetime(): self
    {
        return $this->setType('datetime');
    }

    public function phone(): self
    {
        return $this->setType('phone');
    }

    public function price(): self
    {
        return $this->setType('price');
    }

    public function badge(): self
    {
        return $this->setType('badge');
    }

    public function status(): self
    {
        return $this->setType('status');
    }

    public function activeBadge(bool $clickable = false): self
    {
        return $this->presetBadge('active', $clickable);
    }

    public function visibleBadge(bool $clickable = false): self
    {
        return $this->presetBadge('visible', $clickable);
    }

    public function evidenceBadge(bool $clickable = false): self
    {
        return $this->presetBadge('evidence', $clickable);
    }

    public function booleanBadge(?string $column = null): self
    {
        $this->setType('badge');

        return $this->schema('badge', [
            'preset' => null,
            'column' => $column ?? $this->name,
            'variant' => 'automaticResize',
            'clickable' => false,
        ]);
    }

    public function badgeOn(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        return $this->mergeBadge('on', [
            'text' => $text, 'icon' => $icon, 'color' => $color, 'button' => $buttonText,
        ]);
    }

    public function badgeOff(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        return $this->mergeBadge('off', [
            'text' => $text, 'icon' => $icon, 'color' => $color, 'button' => $buttonText,
        ]);
    }

    public function badgeVariant(string $variant): self
    {
        return $this->mergeBadge('variant', trim($variant));
    }

    public function badgeClickable(bool $clickable = true): self
    {
        return $this->mergeBadge('clickable', $clickable);
    }

    private function presetBadge(string $preset, bool $clickable): self
    {
        $this->setType('badge');

        return $this->schema('badge', [
            'preset' => $preset,
            'column' => $this->name,
            'variant' => 'automaticResize',
            'clickable' => $clickable,
        ]);
    }

    private function mergeBadge(string $key, mixed $value): self
    {
        $badge = (array) ($this->schema['badge'] ?? [
            'preset' => null,
            'column' => $this->name,
            'variant' => 'automaticResize',
            'clickable' => false,
        ]);

        $badge[$key] = $value;

        return $this->schema('badge', $badge);
    }

    public function user(): self
    {
        return $this->setType('user');
    }

    public function userAvatar(): self
    {
        return $this->setType('user_avatar');
    }

    public function userName(): self
    {
        return $this->setType('user_name');
    }

    public function icon(): self
    {
        return $this->setType('icon');
    }

    public function image(): self
    {
        return $this->setType('image');
    }

    public function button(): self
    {
        return $this->setType('button');
    }

    public function link($link): self
    {
        if ($link === 'edit') {
            $link = 'modify';
        }

        return parent::link($link);
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
        foreach ($actions as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $this->action($value, true);
                continue;
            }

            if (is_string($key)) {
                $this->action($key, (bool) $value);
            }
        }

        return $this;
    }
}
