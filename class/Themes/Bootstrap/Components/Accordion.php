<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\HasColumns;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;

class Accordion extends Component
{
    use CanSpanColumn, HasColumns, HasGap, RendersText, RendersComponentAttributes, RendersThemeComponents;

    public function render($class): string
    {
        $content = $this->escapeText(trim($class->getDescription()))
            .$this->renderThemeComponents($class->components, 'bootstrap');

        return '<div class="'.$this->getColumnSpan($class->columnSpan).'">'
            .$this->renderInner($class, $content)
            .'</div>';
    }

    /**
     * L'accordion attorno a un contenuto già reso.
     *
     * Serve al layout dei form delle Resource, che rende i figli da sé per
     * dare a ognuno la larghezza giusta e poi chiede qui solo la cornice —
     * come fa già con il Container.
     *
     * @param array<int, string>|null $bodyClasses classi del corpo; `null`
     *        lascia decidere alle colonne dichiarate sull'accordion
     */
    public function renderInner(object $class, string $content, ?array $bodyClasses = null): string
    {
        $schema = $class->getSchema();
        $title = $this->escapeText(trim($class->getText()));
        $expanded = (bool) ($schema['expanded'] ?? false);
        $flush = (bool) ($schema['flush'] ?? false);
        $id = $this->createId();

        $collapsed = $expanded ? '' : ' collapsed';
        $show = $expanded ? ' show' : '';
        $ariaExpanded = $expanded ? 'true' : 'false';
        $accordionClasses = ['accordion'];

        if ($flush) {
            $accordionClasses[] = 'accordion-flush';
        }

        $attributes = $this->renderComponentAttributes($class, $accordionClasses);

        // Il corpo è un contenitore a griglia come il `card-body` della Card:
        // senza, i campi di un form — che portano `col-span-*` — non hanno
        // nessuna riga in cui stare e si schiacciano in una striscia. Le
        // classi compaiono solo se qualcuno ha chiesto le colonne, così un
        // accordion di solo testo resta quello di sempre.
        if ($bodyClasses === null) {
            $bodyClasses = [
                is_array($class->columns ?? null) ? $this->getColumns($class->columns) : '',
                is_array($class->gap ?? null) ? $this->getGap($class->gap) : '',
            ];
        }

        if ((bool) ($schema['link'] ?? false)) {
            return $this->renderLink($class, $content, $bodyClasses, $title, $id, $expanded);
        }

        $bodyClass = trim(implode(' ', array_filter(array_merge(['accordion-body'], $bodyClasses))));

        $html = "<div {$attributes}>";
        $html .= '<div class="accordion-item">';
        $html .= '<div class="accordion-header">';
        $html .= "<button class=\"accordion-button{$collapsed}\" type=\"button\""
            . " data-bs-toggle=\"collapse\" data-bs-target=\"#{$id}\""
            . " aria-expanded=\"{$ariaExpanded}\" aria-controls=\"{$id}\">"
            . "{$title}</button>";
        $html .= '</div>';
        $html .= "<div id=\"{$id}\" class=\"accordion-collapse collapse{$show}\">";
        $html .= "<div class=\"{$bodyClass}\">{$content}</div>";
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * La variante a link: il bottone di testo di «Compila le informazioni
     * avanzate» e sotto il corpo, senza bordi. La freccia gira con il CSS
     * della lib (`.wi-accordion-link`); senza, resta ferma e basta.
     *
     * @param array<int, string> $bodyClasses
     */
    private function renderLink(object $class, string $content, array $bodyClasses, string $title, string $id, bool $expanded): string
    {
        $attributes = $this->renderComponentAttributes($class, ['wi-accordion-link']);
        $bodyClass = trim(implode(' ', array_filter(array_merge(['pt-2'], $bodyClasses))));

        return "<div {$attributes}>"
            .'<button class="btn btn-link btn-sm px-0 text-decoration-none'.($expanded ? '' : ' collapsed').'" type="button"'
            ." data-bs-toggle=\"collapse\" data-bs-target=\"#{$id}\""
            .' aria-expanded="'.($expanded ? 'true' : 'false')."\" aria-controls=\"{$id}\">"
            .'<i class="bi bi-chevron-down me-1"></i>'.$title
            .'</button>'
            ."<div id=\"{$id}\" class=\"collapse".($expanded ? ' show' : '').'">'
            ."<div class=\"{$bodyClass}\">{$content}</div>"
            .'</div>'
            .'</div>';
    }
}
