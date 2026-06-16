<?php

namespace Wonder\Themes\Bootstrap\Components;

/**
 * Stesso renderer di `Text` ma con tag fisso `<div>` (non `<p>`) per
 * coerenza col markup precedente. Mantiene il comportamento storico:
 * 5 call site in `SecurityResource` infilano HTML grezzo dentro
 * `HelpText::make('<a>...')` — questo è il punto in cui veniva
 * silenziosamente fatto escape, ora funziona perché `HelpText`
 * costruisce con `->html(true)` di default.
 */
class HelpText extends Text
{
    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = $this->textClasses($schema);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);
        $body = $this->renderHelpBody($class, $schema);

        $classAttr = $this->buildClassAttribute($classes);

        return "<div class=\"{$classSpanColumn}\">"
            ."<div{$classAttr} {$attributes}>{$body}</div>"
            .'</div>';
    }

    private function renderHelpBody(object $class, array $schema): string
    {
        $parts = (array) ($schema['parts'] ?? []);
        $rawHtml = (bool) ($schema['html'] ?? false);

        // Reuse della logica del renderer Text (composizione parts + Link
        // inline). Lo replichiamo qui invece di estendere per evitare di
        // copiare il tag `<p>` che HelpText non vuole.
        if ($parts === []) {
            $legacy = method_exists($class, 'getText') ? (string) $class->getText() : '';

            return $rawHtml ? $legacy : $this->escapeText($legacy);
        }

        $output = '';

        foreach ($parts as $part) {
            if (is_string($part)) {
                $output .= $rawHtml ? $part : $this->escapeText($part);
                continue;
            }

            $output .= $this->renderInlineLink($part);
        }

        return $output;
    }
}
