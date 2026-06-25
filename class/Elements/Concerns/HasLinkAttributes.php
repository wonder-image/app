<?php

namespace Wonder\Elements\Concerns;

use Wonder\Elements\Components\Link;

trait HasLinkAttributes
{
    public function href(string $href): static
    {
        $href = trim($href);

        if ($href === '') {
            return $this->removeAttr('href');
        }

        return $this->attr('href', $href);
    }

    public function getHref(): string
    {
        $href = $this->getAttr('href');

        return is_scalar($href) ? trim((string) $href) : '';
    }

    public function target(string $target): static
    {
        $target = trim($target);

        if ($target === '') {
            return $this->removeAttr('target');
        }

        return $this->attr('target', $target);
    }

    public function blank(bool $blank = true): static
    {
        if (!$blank) {
            $this->removeAttr('target');
            $this->removeAttr('rel');

            return $this;
        }

        $tokens = preg_split('/\s+/', (string) ($this->getAttr('rel') ?? ''), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens[] = 'noopener';
        $tokens[] = 'noreferrer';
        $tokens = array_values(array_unique(array_map('trim', $tokens)));

        return $this
            ->attr('target', '_blank')
            ->attr('rel', implode(' ', array_filter($tokens)));
    }

    public function external(bool $external = true): static
    {
        return $this->blank($external);
    }

    public function rel(string $rel): static
    {
        $rel = trim($rel);

        if ($rel === '') {
            return $this->removeAttr('rel');
        }

        return $this->attr('rel', $rel);
    }

    public function title(string $title): static
    {
        $title = trim($title);

        if ($title === '') {
            return $this->removeAttr('title');
        }

        return $this->attr('title', $title);
    }

    public function onclick(string $onclick): static
    {
        $onclick = trim($onclick);

        if ($onclick === '') {
            return $this->removeAttr('onclick');
        }

        return $this->attr('onclick', $onclick);
    }

    public function download(bool|string $download = true): static
    {
        if ($download === false) {
            return $this->removeAttr('download');
        }

        if ($download === true) {
            return $this->attr('download', true);
        }

        $download = trim($download);

        if ($download === '') {
            return $this->removeAttr('download');
        }

        return $this->attr('download', $download);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function applyLinkOptions(Link $link, array $options): Link
    {
        if (isset($options['href']) && is_string($options['href'])) {
            $link->href($options['href']);
        }

        if (isset($options['attributes']) && is_array($options['attributes'])) {
            $link->attributes($options['attributes']);
        }

        if (isset($options['target']) && is_string($options['target'])) {
            $link->target($options['target']);
        }

        if (isset($options['rel']) && is_string($options['rel'])) {
            $link->rel($options['rel']);
        }

        if (!empty($options['blank']) || !empty($options['external'])) {
            $link->blank(true);
        }

        if (isset($options['title']) && is_string($options['title'])) {
            $link->title($options['title']);
        }

        if (isset($options['onclick']) && is_string($options['onclick'])) {
            $link->onclick($options['onclick']);
        }

        if (array_key_exists('download', $options)) {
            $download = $options['download'];

            if (is_bool($download) || is_string($download)) {
                $link->download($download);
            }
        }

        if (isset($options['icon']) && is_string($options['icon']) && $options['icon'] !== '') {
            $link->icon($options['icon'], (string) ($options['icon_position'] ?? 'start'));
        }

        if (isset($options['class']) && is_string($options['class']) && $options['class'] !== '') {
            $link->class($options['class']);
        }

        if (!empty($options['muted'])) {
            $link->muted(true);
        }

        return $link;
    }
}
