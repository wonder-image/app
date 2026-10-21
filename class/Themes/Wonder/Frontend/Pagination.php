<?php

namespace Wonder\Themes\Wonder\Frontend;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\TranslatesLabels;
use Wonder\Themes\Wonder\Component;

/** Lo stesso markup di `frontend.account.pagination`, a spazi tra i tag esclusi. */
class Pagination extends Component
{
    use RendersComponentAttributes, TranslatesLabels;

    public function render($class): string
    {
        $schema = $class->getSchema();

        if (($schema['hidden'] ?? false) === true) {
            return '';
        }

        $page = $class->page();
        $pages = $class->pages();
        $summary = $schema['summary'] ?? null;
        $label = (string) ($schema['label'] ?? $this->translateLabel('components.pagination.label', 'Pagine'));
        $previous = (string) ($schema['previous_label'] ?? $this->translateLabel('components.pagination.previous', 'Pagina precedente'));
        $next = (string) ($schema['next_label'] ?? $this->translateLabel('components.pagination.next', 'Pagina successiva'));

        $items = $this->item($class, $page - 1, '<i class="bi bi-chevron-left" aria-hidden="true"></i>', $previous, false, $page <= 1);

        foreach ($class->visiblePages() as $number) {
            $items .= $this->item($class, $number, (string) $number, '', $number === $page);
        }

        $items .= $this->item($class, $page + 1, '<i class="bi bi-chevron-right" aria-hidden="true"></i>', $next, false, $page >= $pages);

        $ariaLabel = ' aria-label="'.$this->escape($label).'"';

        // Classi e attributi dati vanno sul tag esterno: il piede col riepilogo o l'elenco.
        if (!is_string($summary)) {
            return '<ul '.$this->renderComponentAttributes($class, ['wi-pagination']).$ariaLabel.'>'.$items.'</ul>';
        }

        return '<div '.$this->renderComponentAttributes($class, ['wi-row-table__foot']).'>'
            .'<span>'.$this->escape($summary).'</span>'
            .'<ul class="wi-pagination"'.$ariaLabel.'>'.$items.'</ul>'
            .'</div>';
    }

    private function item(object $class, int $page, string $label, string $aria = '', bool $current = false, bool $disabled = false): string
    {
        $attributes = $disabled
            ? ' aria-disabled="true" tabindex="-1"'
            : ' href="'.$this->escape($class->pageUrl($page)).'"'.($current ? ' aria-current="page"' : '');

        return '<li><a class="wi-pagination__item"'.$attributes.($aria !== '' ? ' aria-label="'.$this->escape($aria).'"' : '').'>'.$label.'</a></li>';
    }
}
