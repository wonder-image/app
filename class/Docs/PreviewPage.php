<?php

namespace Wonder\Docs;

use InvalidArgumentException;
use Wonder\App\Dependencies;
use Wonder\App\Support\Asset;
use Wonder\App\Theme;
use Wonder\View\CssTokens;

/**
 * La pagina che vive dentro l'iframe di un'anteprima: un documento minimo con
 * i soli asset del tema chiesto e l'HTML di un esempio.
 *
 * È la stessa per il backend di un sito e per il server autonomo: cambia solo
 * da dove arrivano i token CSS del tema Wonder (il `root.css` del sito, se
 * c'è, altrimenti i default del framework).
 */
final class PreviewPage
{
    /**
     * Risolve i parametri della richiesta e rende l'esempio.
     *
     * @param array<string, mixed> $query `component`, `example`, `theme`, `scheme`
     * @return array<string, mixed> le variabili della vista `pages/docs/preview.php`
     */
    public static function build(array $query, string $rootApp, bool $standalone = false): array
    {
        $slug = strtolower(trim((string) ($query['component'] ?? '')));
        $doc = Catalog::find($slug);

        if ($doc === null) {
            throw new InvalidArgumentException('Componente non trovato: '.$slug);
        }

        $index = (int) ($query['example'] ?? 0);
        $example = $doc->getExample($index);

        if ($example === null) {
            throw new InvalidArgumentException('Esempio non trovato: '.$index);
        }

        $theme = strtolower(trim((string) ($query['theme'] ?? 'bootstrap')));

        if (!in_array($theme, ThemeSupport::themes(), true)) {
            throw new InvalidArgumentException('Tema non valido: '.$theme);
        }

        $scheme = strtolower(trim((string) ($query['scheme'] ?? 'light'))) === 'dark' ? 'dark' : 'light';

        self::loadDependencies($theme, $rootApp);

        // Prima il render, poi la testata: i renderer accendono da soli le
        // dipendenze che gli servono (Swiper, Fancyapps, Moment).
        $result = ExampleRunner::render($doc, $example, $theme);

        return [
            'doc' => $doc,
            'example' => $example,
            'index' => $index,
            'theme' => $theme,
            'scheme' => $theme === 'bootstrap' ? $scheme : 'light',
            'result' => $result,
            'tokens' => $theme === 'wonder' ? self::wonderTokens($standalone) : '',
            'title' => $doc->getTitle().' · '.$example->getTitle().' · '.ThemeSupport::label($theme),
        ];
    }

    /** Solo gli asset del tema: quelli dell'area backend per Bootstrap, quelli del frontend per Wonder. */
    public static function loadDependencies(string $theme, string $rootApp): void
    {
        Dependencies::reset();

        if ($theme === 'bootstrap') {
            // Lo stesso set dell'area backend, dallo stesso file.
            require rtrim($rootApp, '/').'/bootstrap/backend.php';

            return;
        }

        Theme::set($theme);

        // Lo stesso set di app/bootstrap/frontend.php, senza la parte che
        // legge il database del sito.
        Dependencies::jquery()
            ::bootstrapIcons()
            ::jqueryPlugin()
            ::wiLib()
            ::wiFrontend()
            ::moment();
    }

    /**
     * I design token del tema Wonder: il `set-up/root.css` e `color.css` del
     * sito quando esistono, altrimenti i default del framework.
     */
    public static function wonderTokens(bool $standalone): string
    {
        if (!$standalone && defined('APP_URL') && defined('ASSETS_VERSION')) {
            $base = APP_URL.'/assets/'.ASSETS_VERSION.'/css/set-up';
            $root = Asset::path($base.'/root.css');
            $color = Asset::path($base.'/color.css');

            if ($root !== null) {
                $css = (string) file_get_contents($root);
                $css .= $color !== null ? "\n".(string) file_get_contents($color) : "\n".CssTokens::defaultColorClasses();

                return '<style data-wi-docs-tokens="site">'.$css.'</style>';
            }
        }

        return '<style data-wi-docs-tokens="default">'.CssTokens::defaultRoot()."\n".CssTokens::defaultColorClasses().'</style>';
    }
}
