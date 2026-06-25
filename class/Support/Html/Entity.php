<?php

namespace Wonder\Support\Html;

class Entity
{
    public static function encode(
        string $value,
        int $flags = ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
        string $encoding = 'UTF-8'
    ): string {
        if ($value === '') {
            return '';
        }

        return htmlspecialchars($value, $flags, $encoding);
    }

    public static function decode(
        string $value,
        int $flags = ENT_QUOTES | ENT_HTML5,
        string $encoding = 'UTF-8'
    ): string {
        if ($value === '') {
            return '';
        }

        $decoded = html_entity_decode($value, $flags, $encoding);

        if (!str_contains($decoded, '&')) {
            return $decoded;
        }

        return preg_replace_callback('/&([A-Za-z][A-Za-z0-9]+);/', function (array $matches) use ($flags, $encoding): string {
            $name = $matches[1];

            $candidates = array_unique([
                $name,
                strtolower($name),
                ucfirst(strtolower($name))
            ]);

            foreach ($candidates as $candidate) {
                $entity = '&' . $candidate . ';';
                $entityDecoded = html_entity_decode($entity, $flags, $encoding);

                if ($entityDecoded !== $entity) {
                    return $entityDecoded;
                }
            }

            return $matches[0];
        }, $decoded) ?? $decoded;
    }
}

