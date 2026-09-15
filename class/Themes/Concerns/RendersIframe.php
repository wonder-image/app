<?php

namespace Wonder\Themes\Concerns;

use Wonder\App\Dependencies;
use Wonder\Elements\Media\Deferred;

trait RendersIframe
{
    use RendersMediaAttributes;

    protected function renderIframe(object $class): string
    {
        if ($class->getSchema('deferred-mode')) {
            return $this->renderDeferredIframe($class);
        }
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

    protected function renderDeferredIframe(object $class): string
    {
        $iframe = clone $class;
        $iframe->deferred(false)->schema('deferred-content', true)->attr('loading', 'eager');
        $wrapper = Deferred::make($iframe)
            ->mode($class->getSchema('deferred-mode'))->fallbackUrl($class->srcUrl());
        $button = $class->getSchema('deferred-button');
        if ($button !== null) {
            $wrapper->button($button);
        }
        $ratio = $class->getStyle('aspect-ratio');
        $width = $class->getAttr('width');
        $height = $class->getAttr('height');
        if ($ratio) {
            $wrapper->ratio($ratio);
        } elseif (is_numeric($width) && is_numeric($height) && $width > 0 && $height > 0) {
            $wrapper->ratio($width.':'.$height);
        } elseif ($class->getSchema('fit-cover') || $class->getSchema('fit-contain')) {
            $wrapper->fill();
        }
        foreach (['width', 'height', 'max-width', 'min-width', 'min-height', 'max-height', 'border-radius', 'margin'] as $property) {
            if (($value = $class->getStyle($property)) !== null) {
                $wrapper->style($property, $value);
                $iframe->removeStyle($property);
            }
        }
        $classes = $class->getAttr('class');
        if ($classes) {
            $wrapper->attr('class', $classes);
            $iframe->removeAttr('class');
        }
        $iframe->style('width', '100%')->style('height', '100%')
            ->style('display', 'block')->style('border-radius', 'inherit')->removeStyle('aspect-ratio');

        return $wrapper->render($this->iframeTheme());
    }

    protected function iframeTheme(): string
    {
        return 'bootstrap';
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
            . $this->expandBindScript($group, (bool) $class->getSchema('deferred-content'));
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
    protected function expandBindScript(string $group, bool $deferred = false): string
    {
        static $bound = [];

        if (!$deferred && isset($bound[$group])) {
            return '';
        }

        if (!$deferred) { $bound[$group] = true; }

        return '<script>(function(){const bind=function(){'
            . 'if(typeof Fancybox!=="undefined"){Fancybox.bind(\'[data-fancybox="' . $group . '"]\',{Html:{autoSize:false}});}'
            . '};if(document.readyState!=="loading"){bind();}'
            . 'window.addEventListener('.json_encode($this->expandLoadEvent()).',bind,{once:true});'
            . '})();</script>';
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
