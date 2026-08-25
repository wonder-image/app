<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\FormatsDateValue;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;
use Wonder\Elements\Form\Components\DateRange;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Intervallo di date (da / a) in un solo controllo.
 *
 * Il value è la coppia `[from, to]` in `d/m/Y`; se non viene passato, al
 * render viene ricostruito dai POST `<name>_from` / `<name>_to`.
 */
class InputDateRange extends Input
{
    use FormatsDateValue;
    use HasDateBounds;

    protected string $helper = 'dateRange';

    protected function element(): ElementField
    {
        return new DateRange($this->name);
    }

    /**
     * La coppia `[from, to]` in `d/m/Y`. Senza value esplicito viene
     * ricostruita dai POST `<name>_from` / `<name>_to` già normalizzati in
     * `$GLOBALS['VALUES']`.
     *
     * @return array{0: string, 1: string}
     */
    protected function elementValue(): array
    {
        $value = parent::elementValue();

        if (is_array($value)) {
            return $this->formatDateRangePair($value);
        }

        return $this->formatDateRangePair([
            $GLOBALS['VALUES'][$this->name.'_from'] ?? null,
            $GLOBALS['VALUES'][$this->name.'_to'] ?? null,
        ]);
    }

    /**
     * @param array<int, mixed> $value
     * @return array{0: string, 1: string}
     */
    private function formatDateRangePair(array $value): array
    {
        return [
            $this->formatDateString((string) ($value[0] ?? ''), 'd/m/Y') ?? '',
            $this->formatDateString((string) ($value[1] ?? ''), 'd/m/Y') ?? '',
        ];
    }
}
