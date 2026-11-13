<?php

namespace Wonder\Backend\Table;

use Closure;
use Wonder\App\ResourceSchema\TableColumn;

/**
 * I segnali di riga di una lista: il pallino giallo o rosso nella prima
 * colonna, con il motivo nel tooltip.
 *
 * La Resource dichiara i segnali predefiniti e li passa a `resolve()` con il
 * nome della lista (`companies`, `contacts`, ...). Il sito e gli altri moduli
 * li cambiano per nome, da `custom/function/function.php` o dal loro avvio:
 *
 *     RowSignals::add('companies', RowSignal::warning('missing_logo', "...", 'Manca il logo'));
 *     RowSignals::remove('companies', 'missing_brand');
 *
 * Le modifiche si applicano quando la lista si disegna, quindi l'ordine di
 * caricamento non conta. `add()` con un codice che esiste lo sostituisce.
 */
final class RowSignals
{
    public const ALIAS = 'row_signals';

    /** @var array<string, list<array{0: string, 1: RowSignal|string}>> */
    private static array $changes = [];

    public static function add(string $list, RowSignal $signal): void
    {
        self::$changes[$list][] = ['add', $signal];
    }

    public static function remove(string $list, string $code): void
    {
        self::$changes[$list][] = ['remove', $code];
    }

    /** Solo per i test. */
    public static function reset(): void
    {
        self::$changes = [];
    }

    /**
     * I segnali della lista: i predefiniti con le modifiche registrate, senza
     * quelli spenti da `when()`.
     *
     * @param list<RowSignal> $defaults
     * @return list<RowSignal>
     */
    public static function resolve(string $list, array $defaults): array
    {
        $signals = [];

        foreach ($defaults as $signal) {
            $signals[$signal->code] = $signal;
        }

        foreach (self::$changes[$list] ?? [] as [$action, $value]) {
            if ($action === 'add' && $value instanceof RowSignal) {
                $signals[$value->code] = $value;
            } elseif ($action === 'remove') {
                unset($signals[(string) $value]);
            }
        }

        return array_values(array_filter($signals, static fn (RowSignal $s): bool => $s->active()));
    }

    /**
     * La colonna calcolata da aggiungere al `select()` della lista: i codici
     * accesi, separati da virgola.
     *
     * @param list<RowSignal> $signals
     */
    public static function selectSql(array $signals, string $table): string
    {
        if ($signals === []) {
            return "'' AS ".self::ALIAS;
        }

        $parts = array_map(
            static fn (RowSignal $s): string => "IF(({$s->sql($table)}), '{$s->code}', NULL)",
            $signals
        );

        return "CONCAT_WS(',', ".implode(', ', $parts).') AS '.self::ALIAS;
    }

    /**
     * La condizione «almeno un segnale acceso», per un filtro: l'alias del
     * select non entra nel WHERE.
     *
     * @param list<RowSignal> $signals
     */
    public static function whereAny(array $signals, string $table, ?string $level = null): string
    {
        $parts = [];

        foreach ($signals as $signal) {
            if ($level === null || $signal->level === $level) {
                $parts[] = '('.$signal->sql($table).')';
            }
        }

        return $parts === [] ? '0 = 1' : '('.implode(' OR ', $parts).')';
    }

    /**
     * I segnali accesi su una riga letta con `selectSql()`.
     *
     * @param list<RowSignal> $signals
     * @param array<string, mixed> $row
     * @return list<RowSignal>
     */
    public static function fromRow(array $signals, array $row): array
    {
        $codes = array_filter(explode(',', (string) ($row[self::ALIAS] ?? '')));

        return array_values(array_filter($signals, static fn (RowSignal $s): bool => in_array($s->code, $codes, true)));
    }

    /**
     * Il pallino: rosso se c'è almeno un `danger`, altrimenti giallo. Il
     * tooltip elenca i motivi, prima i rossi. Nessun segnale, cella vuota.
     *
     * @param list<RowSignal> $on
     */
    public static function dot(array $on): string
    {
        if ($on === []) {
            return '';
        }

        usort($on, static fn (RowSignal $a, RowSignal $b): int => ($a->level === RowSignal::DANGER ? 0 : 1) <=> ($b->level === RowSignal::DANGER ? 0 : 1));

        $level = $on[0]->level === RowSignal::DANGER ? 'danger' : 'warning';
        $messages = array_map(static fn (RowSignal $s): string => $s->message, $on);
        $tooltip = implode('<br>', array_map(static fn (string $m): string => htmlspecialchars($m, ENT_QUOTES), $messages));

        return '<span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="'
            .htmlspecialchars($tooltip, ENT_QUOTES).'">'
            // Pallino piccolo e semitrasparente: solo classi Bootstrap.
            .'<span class="d-inline-block p-1 rounded-circle bg-'.$level.' opacity-50 align-middle" aria-hidden="true"></span>'
            .'<span class="visually-hidden">'.htmlspecialchars(implode('. ', $messages), ENT_QUOTES).'</span>'
            .'</span>';
    }

    /**
     * La prima colonna della lista: senza intestazione, stretta, fuori
     * dall'export. `$signals` si legge al disegno di ogni riga.
     *
     * @param Closure(): list<RowSignal> $signals
     */
    public static function column(Closure $signals): TableColumn
    {
        $resolved = null;

        return TableColumn::key(self::ALIAS)
            ->text()
            ->label('')
            ->size('little')
            ->exportable(false)
            ->formatter(static function (array $row) use ($signals, &$resolved): string {
                // Una volta per disegno, non una per riga.
                $resolved ??= $signals();

                return self::dot(self::fromRow($resolved, $row));
            });
    }
}
