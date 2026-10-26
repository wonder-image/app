<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\ReadsAlertSchema;
use Wonder\Themes\Wonder\Component;

class Alert extends Component
{
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

        $html = "<div id='{$safeId}' class='wi-alert wi-show' {$attributes}>";
        $html .= "<div class='wi-alert-header'>";
        $html .= $icon . " <b>{$safeTitle}</b>";

        if ($dismissible) {
            $html .= "<i class='wi-alert-close bi bi-x-lg' "
                . "onclick=\"this.parentElement.parentElement.classList.remove('wi-show')\"></i>";
        }

        $html .= "</div>";
        $html .= "<div class='wi-alert-body'>" . $message . "</div>";
        $html .= "</div>";

        return $html;
    }

    private function iconByType(string $type): string
    {
        return match ($type) {
            'danger' => "<i class='wi-alert-icon bi bi-exclamation-triangle tx-danger'></i>",
            'success' => "<i class='wi-alert-icon bi bi-check2-circle tx-success'></i>",
            'warning' => "<i class='wi-alert-icon bi bi-exclamation-triangle tx-warning'></i>",
            default => "<i class='wi-alert-icon bi bi-info-circle tx-primary'></i>",
        };
    }
}
