<?php

namespace Wonder\Docs;

/**
 * Gli URL del catalogo per l'host che lo serve: il backend di un sito
 * (`/backend/app/docs/components`) o il server autonomo del pacchetto (`/`).
 * Le viste non costruiscono URL a mano: chiedono qui.
 */
final class Urls
{
    public const GUIDE = 'https://github.com/wonder-image/app/blob/main/docs/app/';

    private readonly string $base;

    public function __construct(string $base = '', private readonly string $guideBase = self::GUIDE)
    {
        $this->base = rtrim(trim($base), '/');
    }

    public function base(): string
    {
        return $this->base;
    }

    public function index(): string
    {
        return $this->base.'/';
    }

    public function component(string $slug): string
    {
        return $this->base.'/'.rawurlencode($slug).'/';
    }

    public function preview(string $slug, int $example, string $theme, ?string $scheme = null): string
    {
        $query = ['component' => $slug, 'example' => $example, 'theme' => $theme];

        if ($scheme !== null) {
            $query['scheme'] = $scheme;
        }

        return $this->base.'/preview/?'.http_build_query($query);
    }

    /** Un rimando alla guida GitBook: percorso relativo a `docs/app/` o URL assoluto. */
    public function guide(string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        return rtrim($this->guideBase, '/').'/'.ltrim($href, '/');
    }
}
