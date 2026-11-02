<?php

namespace Wonder\App\Support;

/**
 * Gli URL versionati delle misure dell'icona app (`nome-{size}.ext`).
 *
 * Le misure stanno accanto all'originale: la cartella si legge dall'URL
 * dell'icona, non si ricostruisce a mano, perché la resource che ospita i
 * loghi può cambiare cartella. Ogni URL passa da `Asset::version()`.
 */
final class AppIconLinks
{
    /**
     * @param iterable<int|string> $sizes
     * @return array<int, string> misura => URL
     */
    public static function sized(string $appIcon, iterable $sizes, ?string $root = null, ?string $appUrl = null): array
    {
        $pathInfo = pathinfo($appIcon);
        $directory = rtrim((string) ($pathInfo['dirname'] ?? ''), '/');
        $name = (string) ($pathInfo['filename'] ?? '');
        $extension = (string) ($pathInfo['extension'] ?? '');

        if ($appIcon === '' || $name === '' || $extension === '') {
            return [];
        }

        $urls = [];

        foreach ($sizes as $size) {
            $size = (int) $size;
            $urls[$size] = Asset::version("{$directory}/{$name}-{$size}.{$extension}", $root, $appUrl);
        }

        return $urls;
    }
}
