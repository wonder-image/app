<?php

namespace Wonder\Elements\Media;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\HasMediaFit;
use Wonder\Elements\Concerns\Renderer;

class Iframe extends Component
{
    use HasMediaFit;
    use Renderer;

    public function __construct(string $url)
    {
        $this->schema('src', $this->validateUrl($url))
            ->attr('loading', 'lazy')
            ->style('border', '0');
    }

    public static function url(string $url): self
    {
        return new self($url);
    }

    public function srcUrl(): string
    {
        return $this->validateUrl((string) $this->getSchema('src'));
    }

    private function validateUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('L\'URL iframe non puo essere vuoto.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new InvalidArgumentException('L\'URL iframe contiene caratteri di controllo non validi.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme) && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                "Schema URL iframe {$scheme} non consentito. Usa http, https o un URL relativo."
            );
        }

        return $url;
    }
}
