<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Wonder\Component;

class Choice extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $icon = (string) ($schema['icon'] ?? '');

        $input = $this->renderAttributes([
            'type' => (string) $schema['type'],
            'name' => (string) $schema['name'],
            'value' => (string) $schema['value'],
            'checked' => (bool) $schema['checked'],
            'disabled' => (bool) $schema['disabled'],
            'data-choice-input' => true,
        ]);

        return '<label '.$this->renderComponentAttributes($class, ['wi-choice']).'>'
            .'<input '.$input.'>'
            .($icon !== '' ? '<i class="wi-choice__icon bi bi-'.$this->escape($icon).'" aria-hidden="true"></i>' : '')
            .'<span class="wi-choice__body">'
            .$this->part('wi-choice__title', 'data-choice-title', (string) $schema['title'])
            .$this->part('wi-choice__text', 'data-choice-text', (string) $schema['text'])
            .'</span>'
            .$this->icons((array) ($schema['icons'] ?? []), (int) ($schema['icons_max'] ?? 3))
            .$this->part('wi-choice__aside', 'data-choice-aside', (string) $schema['aside'])
            .$this->part('wi-choice__panel', 'data-choice-panel', (string) ($schema['panel'] ?? ''))
            .'</label>';
    }

    /** @param array<int, array{src: string, alt: string}> $icons */
    private function icons(array $icons, int $max): string
    {
        $html = '';

        foreach (array_slice($icons, 0, $max) as $icon) {
            $html .= '<img '.$this->renderAttributes([
                'src' => $icon['src'],
                'alt' => $icon['alt'],
                'width' => '38',
                'height' => '24',
                'loading' => 'lazy',
            ]).'>';
        }

        if (count($icons) > $max) {
            $html .= '<span class="wi-choice__more">+'.(count($icons) - $max).'</span>';
        }

        return '<span class="wi-choice__icons" data-choice-icons'.($html === '' ? ' hidden' : '').'>'.$html.'</span>';
    }

    private function part(string $class, string $hook, string $value): string
    {
        return '<span class="'.$class.'" '.$hook.($value === '' ? ' hidden' : '').'>'.$this->escape($value).'</span>';
    }
}
