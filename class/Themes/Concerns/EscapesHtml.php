<?php

namespace Wonder\Themes\Concerns;

trait EscapesHtml
{
    /**
     * Escape HTML unificato per renderer e attributi.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function escapeWithLineBreaks(string $value): string
    {
        return \Wonder\Support\Html\Entity::encodeWithLineBreaks($value);
    }
}
