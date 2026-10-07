<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;

class Choice extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $icon = (string) ($schema['icon'] ?? '');

        $input = $this->renderAttributes([
            'class' => 'form-check-input',
            'type' => (string) $schema['type'],
            'name' => (string) $schema['name'],
            'value' => (string) $schema['value'],
            'checked' => (bool) $schema['checked'],
            'disabled' => (bool) $schema['disabled'],
            'data-choice-input' => true,
        ]);

        return '<label '.$this->renderComponentAttributes($class, ['card', 'user-select-none']).'>'
            .'<span class="card-body d-flex align-items-start gap-3">'
            .'<span class="form-check m-0"><input '.$input.'></span>'
            .($icon !== '' ? '<i class="bi bi-'.$this->escape($icon).'" aria-hidden="true"></i>' : '')
            .'<span class="flex-grow-1">'
            .$this->part('d-block fw-semibold', 'data-choice-title', (string) $schema['title'])
            .$this->part('d-block small text-body-secondary', 'data-choice-text', (string) $schema['text'])
            .'</span>'
            .$this->icons((array) ($schema['icons'] ?? []), (int) ($schema['icons_max'] ?? 3))
            .$this->part('text-nowrap fw-semibold', 'data-choice-aside', (string) $schema['aside'])
            .'</span>'
            .$this->panel((string) ($schema['panel'] ?? ''))
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
            $html .= '<span class="small text-body-secondary">+'.(count($icons) - $max).'</span>';
        }

        return '<span class="align-items-center gap-1" style="display:flex" data-choice-icons'.($html === '' ? ' hidden' : '').'>'.$html.'</span>';
    }

    /** `style` e non `d-block`: le utility di Bootstrap sono `!important` e batterebbero `[hidden]`. */
    private function panel(string $text): string
    {
        return '<span class="card-footer small" style="display:block;-webkit-user-select:text;user-select:text" data-choice-panel'.($text === '' ? ' hidden' : '').'>'
            .$this->escape($text).'</span>';
    }

    private function part(string $class, string $hook, string $value): string
    {
        return '<span class="'.$class.'" '.$hook.($value === '' ? ' hidden' : '').'>'.$this->escape($value).'</span>';
    }
}
