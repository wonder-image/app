<?php

namespace Wonder\Docs;

/**
 * Una sezione del catalogo (Form, Componenti, Media, Grafici) con i suoi
 * eventuali gruppi interni, ordinati come dichiarati.
 */
final class Category
{
    /** @var array<string, string> */
    private array $groups;

    /**
     * @param array<string, string> $groups chiave => titolo, nell'ordine di visualizzazione
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description = '',
        public readonly int $order = 100,
        public readonly string $icon = '',
        array $groups = [],
    ) {
        $this->groups = [];

        foreach ($groups as $groupKey => $groupTitle) {
            $this->groups[(string) $groupKey] = (string) $groupTitle;
        }
    }

    /** @return array<string, string> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function groupTitle(string $key): string
    {
        return $this->groups[$key] ?? '';
    }

    public function groupOrder(string $key): int
    {
        $position = array_search($key, array_keys($this->groups), true);

        return $position === false ? PHP_INT_MAX : (int) $position;
    }
}
