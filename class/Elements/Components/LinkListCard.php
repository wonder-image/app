<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

/**
 * Card con un elenco di collegamenti, raggruppabili sotto un'intestazione.
 * Con almeno un gruppo i link si dispongono in colonne, una per gruppo.
 */
class LinkListCard extends Component
{
    use CanSpanColumn, Renderer;

    public function __construct(string $title = '')
    {
        $this->schema('title', $title);
        $this->schema('links', []);
        $this->variant('primary');
        $this->count();
        $this->ungroupedTitle('Generale');
    }

    public static function make(string $title): static
    {
        return new static($title);
    }

    /** Classe Bootstrap Icons (`bi-terminal`). */
    public function icon(string $icon): static
    {
        return $this->schema('icon', $icon);
    }

    public function variant(string $variant): static
    {
        return $this->schema('variant', $variant);
    }

    /**
     * Aggiunge un link; `$group` è l'intestazione sotto cui compare, `$badge`
     * un dato breve accanto al titolo (un conteggio) e `$detail` una riga
     * secondaria sotto.
     */
    public function link(string $title, string $href, string $group = '', string $badge = '', string $detail = ''): static
    {
        $links = (array) $this->getSchema('links');
        $links[] = ['title' => $title, 'href' => $href, 'group' => $group, 'badge' => $badge, 'detail' => $detail];

        return $this->schema('links', $links);
    }

    /** @param iterable<array{title: string, href: string, group?: string, badge?: string, detail?: string}> $links */
    public function links(iterable $links): static
    {
        foreach ($links as $link) {
            $this->link(
                (string) ($link['title'] ?? ''),
                (string) ($link['href'] ?? ''),
                (string) ($link['group'] ?? ''),
                (string) ($link['badge'] ?? ''),
                (string) ($link['detail'] ?? ''),
            );
        }

        return $this;
    }

    /** Mostra il numero dei link accanto al titolo. */
    public function count(bool $count = true): static
    {
        return $this->schema('count', $count);
    }

    /** Intestazione dei link senza gruppo quando la card ha gruppi. */
    public function ungroupedTitle(string $title): static
    {
        return $this->schema('ungrouped_title', $title);
    }

    /**
     * Link raggruppati per intestazione nell'ordine di arrivo; quelli senza
     * gruppo stanno nel gruppo dal titolo vuoto.
     *
     * @return list<array{title: string, links: list<array{title: string, href: string, group: string, badge: string, detail: string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ((array) $this->getSchema('links') as $link) {
            $groups[$link['group']] ??= ['title' => $link['group'], 'links' => []];
            $groups[$link['group']]['links'][] = $link;
        }

        return array_values($groups);
    }

    /** Vero se almeno un link ha un gruppo: la card va in colonne. */
    public function isGrouped(): bool
    {
        $groups = $this->groups();

        return count($groups) > 1 || ($groups[0]['title'] ?? '') !== '';
    }
}
