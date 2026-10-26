<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\ReadsAlertSchema;

class Alert extends Component
{
    use HasAttributes;
    use ReadsAlertSchema;

    public function render($class): string
    {
        $alert = $this->readAlertSchema($class);
        $message = $alert['message'];
        $type = $alert['type'];
        $icon = $this->iconByType($type);
        $safeId = $alert['id'];
        $safeTitle = $alert['title'];
        $dismissible = $alert['dismissible'];
        $attributes = $alert['attributes'];

        $html = "<div id='{$safeId}' class='toast border-{$type} overflow-hidden' role='alert' "
            . "aria-live='assertive' aria-atomic='true' {$attributes}>";
        $html .= "<div class='toast-header text-bg-{$type} border-bottom border-{$type}'> {$icon} "
            . "<strong class='me-auto'>{$safeTitle}</strong>";

        if ($dismissible) {
            $html .= "<button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>";
        }

        $html .= '</div>';
        $html .= "<div class='toast-body bg-light'>" . $message . "</div>";
        $html .= '</div>';

        return $html;
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
