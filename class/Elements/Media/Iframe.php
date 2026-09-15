<?php

namespace Wonder\Elements\Media;

use InvalidArgumentException;
use Wonder\Elements\Concerns\HasMediaFit;
use Wonder\Elements\Concerns\HasRatio;
use Wonder\Elements\Components\Button;

class Iframe extends Media
{
    use HasMediaFit, HasRatio;

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

    public function deferred(bool|string $mode = true, ?Button $button = null): self
    {
        $mode = $mode === true ? 'interaction' : $mode;
        if ($mode !== false && !in_array($mode, ['interaction', 'visible'], true)) {
            throw new InvalidArgumentException('Deferred mode must be interaction or visible.');
        }
        if ($button !== null) {
            $this->deferredButton($button);
        }
        return $this->schema('deferred-mode', $mode);
    }

    public function deferredButton(Button $button): self
    {
        return $this->schema('deferred-button', $button);
    }

    /**
     * Rende l'iframe apribile in un lightbox Fancybox (modalità iframe): il tema
     * aggiunge un pulsante "Ingrandisci" in overlay e registra il bind. Utile per
     * mappe, video e virtual tour, che restano interattivi anche inline.
     */
    public function expandable(bool $expandable = true): self
    {
        return $this->schema('expandable', $expandable);
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
