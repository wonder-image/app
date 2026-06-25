<?php

    function stripHtmlElementsWithContent(string $html, array $elements): string
    {

        if ($html === '' || $elements === []) {
            return $html;
        }

        $normalized = [];

        foreach ($elements as $element) {
            $element = strtolower(trim((string) $element));
            if ($element !== '') {
                $normalized[$element] = true;
            }
        }

        if ($normalized === []) {
            return $html;
        }

        $escapedElements = [];
        foreach (array_keys($normalized) as $element) {
            $escapedElements[] = preg_quote($element, '#');
        }

        $pattern = '#<(' . implode('|', $escapedElements) . ')\b[^>]*>.*?</\1>#is';

        return pregReplaceSafe($pattern, '', $html);

    }

    function stripHtmlComments(string $html): string
    {

        return pregReplaceSafe('#<!--.*?-->#s', '', $html);

    }

    function stripHtmlNonTextBlocks(string $html): string
    {

        return stripHtmlElementsWithContent($html, [ 'head', 'style', 'script', 'noscript', 'template' ]);

    }

    function convertHtmlBreaksToNewLine(string $html): string
    {

        return pregReplaceSafe('#<br\s*/?>#i', "\n", $html);

    }

    function htmlToText(string $body): string
    {

        if ($body === '') {
            return '';
        }

        $text = $body;

        $text = stripHtmlNonTextBlocks($text);
        $text = stripHtmlComments($text);

        // Converte i link html in "testo (url)" prima di rimuovere i tag.
        $text = pregReplaceCallbackSafe(
            '#<a\s[^>]*href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^>\s]+))[^>]*>(.*?)</a>#is',
            function (array $matches): string {
                $url = trim((string) ($matches[1] ?? $matches[2] ?? $matches[3] ?? ''));
                $url = \Wonder\Support\Html\Entity::decode($url);

                $label = trim(strip_tags((string) ($matches[4] ?? '')));
                $label = \Wonder\Support\Html\Entity::decode($label);

                if ($url === '') {
                    return $label;
                }

                if ($label === '' || $label === $url) {
                    return $url;
                }

                return $label . ' (' . $url . ')';
            },
            $text
        );

        $text = pregReplaceSafe('#<(br|/p|/div|/li|/tr|/h[1-6])\s*/?>#i', "\n", $text);
        $text = pregReplaceSafe('#<li[^>]*>#i', '- ', $text);

        $text = strip_tags($text);
        $text = \Wonder\Support\Html\Entity::decode($text);

        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = normalizeLineEndings($text);

        $text = pregReplaceSafe("/[ \t]+/", ' ', $text);
        $text = pregReplaceSafe("/\n{3,}/", "\n\n", $text);
        $text = pregReplaceSafe("/ *\n */", "\n", $text);

        return trim((string) $text);

    }
