<?php

namespace Wonder\Backend\Support;

use Throwable;

/**
 * Pulsante "Guida" dell'header delle pagine backend.
 */
final class DocsAction
{
    private const LABEL_KEY = 'components.buttons.docs';

    /**
     * Descriptor compatibile con `PageActionNormalizer` e con il layout `show`.
     *
     * @return array<string, string>
     */
    public static function descriptor(string $url, string $label): array
    {
        return [
            'label' => $label,
            'href' => $url,
            'target' => '_blank',
            'class' => 'btn-info btn-sm',
            'icon' => 'bi bi-question-circle',
        ];
    }

    public static function label(): string
    {
        if (function_exists('__t')) {
            try {
                $label = __t(self::LABEL_KEY);

                if (is_string($label) && trim($label) !== '' && $label !== self::LABEL_KEY) {
                    return trim($label);
                }
            } catch (Throwable) {
            }
        }

        return 'Guida';
    }
}
