<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

class Alert extends Component
{
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

        $html = "<div id='{$safeId}' class='wi-alert wi-show' {$attributes}>";
        $html .= "<div class='wi-alert-header'>";
        $html .= $icon . " <b>{$safeTitle}</b>";

        if ($dismissible) {
            $html .= "<i class='wi-alert-close bi bi-x-lg' "
                . "onclick=\"this.parentElement.parentElement.classList.remove('wi-show')\"></i>";
        }

        $html .= "</div>";
        $html .= "<div class='wi-alert-body'>" . nl2br($message) . "</div>";
        $html .= "</div>";

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
            'danger' => "<i class='wi-alert-icon bi bi-exclamation-triangle tx-danger'></i>",
            'success' => "<i class='wi-alert-icon bi bi-check2-circle tx-success'></i>",
            'warning' => "<i class='wi-alert-icon bi bi-exclamation-triangle tx-warning'></i>",
            default => "<i class='wi-alert-icon bi bi-info-circle tx-primary'></i>",
        };
    }
}
