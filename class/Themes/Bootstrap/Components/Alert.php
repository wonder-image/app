<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClassAttribute;
use Wonder\Themes\Concerns\ReadsAlertSchema;

class Alert extends Component
{
    use HasAttributes, MergesClassAttribute;
    use ReadsAlertSchema;

    public function render($class): string
    {
        $schema = $class->getSchema();

        // Prima di readAlertSchema: in linea non si genera nessun id.
        if ((bool) ($schema['inline'] ?? false)) {
            return $this->renderInline($schema);
        }

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

    /**
     * Avviso di pagina Bootstrap, visibile. Le classi date al componente
     * finiscono nello stesso attributo `class`.
     */
    private function renderInline(array $schema): string
    {
        $type = $this->alertType(strtolower((string) ($schema['level'] ?? 'info')));
        $message = $this->escapeWithLineBreaks((string) ($schema['message'] ?? ''));
        $title = trim((string) ($schema['title'] ?? ''));
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $dismissible = (bool) ($schema['dismissible'] ?? false);
        $id = trim(is_scalar($schema['id'] ?? null) ? (string) $schema['id'] : '');
        $classes = $this->mergeClassAttribute(
            "alert alert-{$type}".($dismissible ? ' alert-dismissible fade show' : ''),
            $attributes['class'] ?? []
        );
        unset($attributes['class']);

        $attributes = array_merge(
            ['class' => $classes],
            $id !== '' ? ['id' => $id] : [],
            ['role' => 'alert'],
            $attributes
        );

        $html = '<div '.$this->renderAttributes($attributes).'>';

        if ($title !== '') {
            $html .= '<strong class="alert-heading d-block">'.$this->escape($title).'</strong>';
        }

        $html .= $message;

        if ($dismissible) {
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }

        return $html.'</div>';
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
