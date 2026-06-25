<?php

    function encodeJsonString($value): string
    {

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';

    }

    function valueToString($value, string $trueValue = '1', string $falseValue = ''): string
    {

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? $trueValue : $falseValue;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            return encodeJsonString($value);
        }

        if (!is_string($value)) {
            return (string) $value;
        }

        return $value;

    }

    function pregReplaceSafe(string $pattern, $replacement, string $subject, int $limit = -1): string
    {

        $result = preg_replace($pattern, $replacement, $subject, $limit);

        return is_string($result) ? $result : $subject;

    }

    function pregReplaceCallbackSafe(string $pattern, callable $callback, string $subject, int $limit = -1): string
    {

        $result = preg_replace_callback($pattern, $callback, $subject, $limit);

        return is_string($result) ? $result : $subject;

    }

    function normalizeLineEndings(string $text, string $lineEnding = "\n"): string
    {

        return str_replace(["\r\n", "\r"], $lineEnding, $text);

    }

    function isValidUtf8String(string $value): bool
    {

        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;

    }
