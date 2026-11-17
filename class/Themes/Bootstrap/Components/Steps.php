<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;

/**
 * Stepper con le classi di Bootstrap: numero in un badge tondo (spunta sui
 * passi fatti), etichetta e una linea verso il passo dopo.
 */
class Steps extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $label = (string) ($schema['label'] ?? '');
        $steps = array_values($schema['steps'] ?? []);
        $items = '';

        foreach ($steps as $i => $step) {
            $text = $this->escape((string) $step['label']);
            $last = $i === count($steps) - 1;

            [$attributes, $badge, $content] = match ($step['state']) {
                'done' => [
                    '',
                    '<span class="badge rounded-pill text-bg-success" aria-hidden="true"><i class="bi bi-check-lg"></i></span>',
                    $step['href'] !== null
                        ? '<a href="'.$this->escape($step['href']).'" class="link-body-emphasis link-underline-opacity-0 link-underline-opacity-100-hover">'.$text.'</a>'
                        : $text,
                ],
                'current' => [
                    ' aria-current="step"',
                    '<span class="badge rounded-pill text-bg-primary" aria-hidden="true">'.($i + 1).'</span>',
                    '<span class="fw-semibold">'.$text.'</span>',
                ],
                default => [
                    ' aria-disabled="true"',
                    '<span class="badge rounded-pill text-bg-light border text-body-tertiary" aria-hidden="true">'.($i + 1).'</span>',
                    '<span class="text-body-tertiary">'.$text.'</span>',
                ],
            };

            $items .= '<li class="d-flex align-items-center gap-2'.($last ? '' : ' flex-grow-1').'"'.$attributes.'>'
                .$badge.$content
                .($last ? '' : '<span class="flex-grow-1 border-top" aria-hidden="true"></span>')
                .'</li>';
        }

        return '<nav'.($label !== '' ? ' aria-label="'.$this->escape($label).'"' : '').'>'
            .'<ol '.$this->renderComponentAttributes($class, ['d-flex', 'flex-wrap', 'align-items-center', 'gap-2', 'list-unstyled', 'mb-0', 'small']).'>'.$items.'</ol></nav>';
    }
}
