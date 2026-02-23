<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;

class Alert extends Component
{
    use HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $message = $this->escape((string) ($schema['message'] ?? ''));
        $title = trim((string) ($schema['title'] ?? ''));
        $level = strtolower((string) ($schema['level'] ?? 'info'));
        $dismissible = (bool) ($schema['dismissible'] ?? true);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);
        $type = $this->themeType($level);
        $icon = $this->iconByType($type);
        $id = $this->resolveId($schema['id'] ?? null);
        $safeId = $this->escape($id);
        $safeTitle = $this->escape($title !== '' ? $title : ucfirst($type));

        $html = "<div id='{$safeId}' class='toast border-{$type} overflow-hidden' role='alert' "
            . "aria-live='assertive' aria-atomic='true' {$attributes}>";
        $html .= "<div class='toast-header text-bg-{$type} border-bottom border-{$type}'> {$icon} "
            . "<strong class='me-auto'>{$safeTitle}</strong>";

        if ($dismissible) {
            $html .= "<button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>";
        }

        $html .= '</div>';
        $html .= "<div class='toast-body bg-light'>" . nl2br($message) . "</div>";
        $html .= '</div>';

        return $html;
    }

    private function themeType(string $level): string
    {
        return match ($level) {
            'error' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };
    }

    private function iconByType(string $type): string
    {
        return match ($type) {
            'danger' => "<i class='bi bi-exclamation-triangle me-2'></i>",
            'success' => "<i class='bi bi-check2-circle me-2'></i>",
            'warning' => "<i class='bi bi-exclamation-triangle me-2'></i>",
            default => "<i class='bi bi-info-circle me-2'></i>",
        };
    }
}
