<?php

namespace Wonder\Themes\Concerns;

use Wonder\App\Dependencies;

trait RendersIframe
{
    use RendersMediaAttributes;

    protected function renderIframe(object $class): string
    {
        $attributes = $this->renderMediaAttributes(
            $class,
            $this->iframeThemeClasses($class),
            ['src' => $class->srcUrl()]
        );

        $iframe = '<iframe' . ($attributes !== '' ? ' ' . $attributes : '') . '></iframe>';

        if ($class->getSchema('expandable') !== true) {
            return $iframe;
        }

        return $this->renderExpandableIframe($class, $iframe);
    }

    /**
     * Avvolge l'iframe con un pulsante "Ingrandisci" che apre la stessa sorgente
     * in un lightbox Fancybox (modalità iframe). Il pulsante è fratello
     * dell'iframe — non figlio del box a rapporto fisso — così le regole di
     * riempimento del contenitore non lo stirano a tutta area.
     */
    protected function renderExpandableIframe(object $class, string $iframe): string
    {
        Dependencies::fancyapps();

        $src   = htmlspecialchars($class->srcUrl(), ENT_QUOTES);
        $label = htmlspecialchars($this->expandLabel(), ENT_QUOTES);
        $group = 'wi-iframe';

        $button = '<a href="javascript:;" class="' . $this->expandButtonClass() . '"'
            . ' data-fancybox="' . $group . '" data-type="iframe" data-src="' . $src . '"'
            . ' title="' . $label . '" aria-label="' . $label . '">' . $this->expandIcon() . '</a>';

        return '<div class="' . $this->expandWrapperClass() . '">' . $iframe . $button . '</div>'
            . $this->expandBindScript($group);
    }

    /**
     * Bind Fancybox del gruppo, emesso una sola volta per richiesta: tutti gli
     * iframe espandibili condividono lo stesso gruppo (galleria unica).
     *
     * `Html.autoSize=false`: senza questa opzione Fancybox prova a misurare il
     * contenuto dell'iframe per adattarne l'altezza, ma con sorgenti cross-origin
     * (Google Maps, virtual tour) la misura fallisce e il modale collassa in una
     * striscia sottile. Disattivandola l'iframe riempie il modale a dimensione
     * piena.
     */
    protected function expandBindScript(string $group): string
    {
        static $bound = [];

        if (isset($bound[$group])) {
            return '';
        }

        $bound[$group] = true;

        return '<script>window.addEventListener(' . json_encode($this->expandLoadEvent()) . ',function(){'
            . 'if(typeof Fancybox!=="undefined"){Fancybox.bind(\'[data-fancybox="' . $group . '"]\',{Html:{autoSize:false}});}'
            . '});</script>';
    }

    /** @return string[] */
    protected function iframeThemeClasses(object $class): array
    {
        return [];
    }

    protected function expandWrapperClass(): string
    {
        // Absolute-fill: il wrapper è il figlio unico del box a rapporto fisso,
        // quindi deve riempirlo come `.ratio > *` (assoluto, inset 0). Così è
        // anche il contesto di posizionamento per il pulsante di overlay.
        return 'position-absolute top-0 start-0 w-100 h-100';
    }

    protected function expandButtonClass(): string
    {
        return 'btn btn-sm btn-light shadow-sm position-absolute top-0 end-0 m-2';
    }

    protected function expandIcon(): string
    {
        return '<i class="bi bi-arrows-fullscreen"></i>';
    }

    protected function expandLabel(): string
    {
        return 'Ingrandisci';
    }

    protected function expandLoadEvent(): string
    {
        return 'load';
    }
}
