<?php

use Wonder\Localization\Machine\Translator;

/** Motore finto: antepone `[lingua] ` e registra ogni chiamata. */
final class FakeTranslator implements Translator
{
    /** @var list<list<string>> */
    public array $calls = [];

    public function key(): string
    {
        return 'fake';
    }

    public function translate(array $html, string $source, string $target): array
    {
        $this->calls[] = $html;

        return array_map(static fn (string $text): string => "[{$target}] {$text}", $html);
    }
}
