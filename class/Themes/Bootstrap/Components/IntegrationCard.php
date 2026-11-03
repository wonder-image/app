<?php

namespace Wonder\Themes\Bootstrap\Components;

use InvalidArgumentException;
use Wonder\Elements\Components\IntegrationCard as IntegrationCardElement;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\RendersLinkCards;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClassAttribute;

class IntegrationCard extends Component
{
    use HasAttributes, MergesClassAttribute, RendersLinkCards;

    private const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    public function render($class): string
    {
        if (!$class instanceof IntegrationCardElement) {
            throw new InvalidArgumentException('Il renderer richiede un IntegrationCard.');
        }

        $schema = $class->getSchema();
        $modal = (string) ($schema['modal'] ?? '');
        $description = trim((string) ($schema['description'] ?? ''));

        $html = $this->cardOpen($class, ['card', 'border', 'h-100']);
        $html .= '<div class="card-body d-flex flex-column gap-3 position-relative">';
        $html .= '<div class="d-flex align-items-start gap-3">';
        $html .= $this->cardIcon($schema);
        $html .= '<div class="flex-grow-1 overflow-hidden">';
        $html .= '<button type="button" class="stretched-link btn btn-link p-0 border-0 link-body-emphasis text-decoration-none text-start fw-semibold"'
            .' data-bs-toggle="modal" data-bs-target="#'.$this->escape($modal).'" aria-haspopup="dialog" aria-controls="'.$this->escape($modal).'">'
            .$this->escape((string) ($schema['title'] ?? ''))
            .'</button>';

        if ($description !== '') {
            $html .= '<div class="small text-body-secondary">'.$this->escape($description).'</div>';
        }

        $html .= '</div>';
        $html .= $this->status($schema['status'] ?? null);
        $html .= '</div>';
        $html .= $this->footer((array) ($schema['badges'] ?? []), (array) ($schema['notices'] ?? []));

        return $html.'</div></div>';
    }

    private function status(mixed $status): string
    {
        if (!is_array($status) || trim((string) ($status['label'] ?? '')) === '') {
            return '';
        }

        $variant = $this->variantOf($status['variant'] ?? null);

        return '<span class="badge rounded-pill flex-shrink-0 d-inline-flex align-items-center gap-1 border border-'.$variant.'-subtle bg-'.$variant.'-subtle text-'.$variant.'-emphasis">'
            .'<i class="bi bi-circle-fill small" aria-hidden="true"></i>'
            .$this->escape((string) $status['label'])
            .'</span>';
    }

    private function footer(array $badges, array $notices): string
    {
        $html = '';
        $pills = '';

        foreach ($badges as $badge) {
            $label = trim((string) ($badge['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $variant = $this->variantOf($badge['variant'] ?? null);
            $icon = $this->icon($badge['icon'] ?? null);

            $pills .= '<span class="badge rounded-pill border border-'.$variant.'-subtle text-'.$variant.'-emphasis bg-body d-inline-flex align-items-center gap-1">'
                .$icon.$this->escape($label)
                .'</span>';
        }

        foreach ($notices as $notice) {
            $text = trim((string) ($notice['text'] ?? ''));

            if ($text !== '') {
                $html .= '<div class="small text-warning-emphasis d-flex align-items-center gap-2">'
                    .$this->icon($notice['icon'] ?? null)
                    .'<span>'.$this->escape($text).'</span>'
                    .'</div>';
            }
        }

        if ($pills !== '') {
            $html = '<div class="d-flex flex-wrap gap-1">'.$pills.'</div>'.$html;
        }

        return $html !== '' ? '<div class="mt-auto d-flex flex-column gap-2">'.$html.'</div>' : '';
    }

    private function variantOf(mixed $variant): string
    {
        return in_array($variant, self::VARIANTS, true) ? $variant : 'secondary';
    }

    private function icon(mixed $icon): string
    {
        return is_string($icon) && preg_match('/^bi-[a-z0-9-]+$/', $icon) === 1
            ? '<i class="bi '.$icon.'" aria-hidden="true"></i>'
            : '';
    }
}
