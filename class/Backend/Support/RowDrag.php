<?php

namespace Wonder\Backend\Support;

/**
 * Righe di una tabella da riordinare trascinandole.
 *
 * Ogni riga ha una maniglia ({@see handle()}): si trascina tenendola, e
 * con le frecce su/giù la stessa maniglia sposta la riga da tastiera. Il
 * resto della riga resta selezionabile e cliccabile come prima.
 *
 * Chi disegna le righe in JS chiama `window.wiRowDrag(tbody, onMove)`
 * una volta: `onMove(tr)` arriva dopo ogni spostamento, quando il DOM è
 * già nell'ordine nuovo. Dal `tbody` parte anche `wi:row-move` (bubbling,
 * `detail.row`). La maniglia la crea `window.wiRowDrag.handle()`, gemella
 * in JS di {@see handle()}.
 *
 * Le righe legate fra loro (un lotto sotto la sua riga, per esempio) si
 * muovono insieme: basta marcarle con `data-wi-row-drag-follow`, e
 * seguono la riga che le precede.
 */
final class RowDrag
{
    public const LABEL = 'Trascina per spostare (frecce su e giù da tastiera)';

    /** @param array<string, string|int> $attributes altri attributi del bottone, escapati */
    public static function handle(array $attributes = []): string
    {
        $extra = '';

        foreach ($attributes as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', (string) $name) === 1) {
                $extra .= ' '.$name.'="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'"';
            }
        }

        return '<button type="button"'.$extra.' class="btn btn-sm btn-link text-body-secondary p-0 px-1"'
            .' style="cursor: grab" data-wi-row-drag aria-label="'.self::LABEL.'" title="'.self::LABEL.'">'
            .'<i class="bi bi-grip-vertical" aria-hidden="true"></i></button>';
    }

    /** Lo script condiviso: si arma una volta sola per pagina. */
    public static function script(): string
    {
        $label = json_encode(self::LABEL, JSON_UNESCAPED_UNICODE);

        return <<<HTML
<script>
window.wiRowDrag = window.wiRowDrag || (function () {
    var LABEL = {$label};

    function group(tr) {
        var rows = [tr];
        var next = tr.nextElementSibling;

        while (next && next.hasAttribute('data-wi-row-drag-follow')) {
            rows.push(next);
            next = next.nextElementSibling;
        }

        return rows;
    }

    function head(tr) {
        while (tr && tr.hasAttribute('data-wi-row-drag-follow') && tr.previousElementSibling) {
            tr = tr.previousElementSibling;
        }

        return tr;
    }

    function place(tbody, rows, before) {
        rows.forEach(function (row) { tbody.insertBefore(row, before); });
    }

    function moved(tbody, tr, onMove) {
        if (typeof onMove === 'function') { onMove(tr); }
        tbody.dispatchEvent(new CustomEvent('wi:row-move', { bubbles: true, detail: { row: tr } }));
    }

    function bind(tbody, onMove) {
        var dragging = null;

        if (tbody.wiRowDrag) { return; }
        tbody.wiRowDrag = true;

        tbody.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest && event.target.closest('[data-wi-row-drag]');
            var tr = handle ? head(handle.closest('tr')) : null;

            if (tr && tbody.contains(tr)) { tr.draggable = true; }
        });

        // Un clic sulla maniglia senza trascinare: la riga torna ferma,
        // altrimenti le caselle dentro non si lascerebbero selezionare.
        tbody.addEventListener('pointerup', function () {
            if (dragging) { return; }
            Array.prototype.forEach.call(tbody.querySelectorAll('tr[draggable="true"]'), function (row) { row.draggable = false; });
        });

        tbody.addEventListener('dragstart', function (event) {
            var tr = event.target.closest && event.target.closest('tr');

            if (!tr || !tr.draggable) { return; }

            dragging = tr;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
            group(tr).forEach(function (row) { row.classList.add('opacity-50'); });
        });

        tbody.addEventListener('dragover', function (event) {
            var over = event.target.closest && event.target.closest('tr');
            var rows;
            var box;

            if (!dragging || !over || !tbody.contains(over)) { return; }

            event.preventDefault();
            over = head(over);
            rows = group(dragging);

            if (rows.indexOf(over) !== -1) { return; }

            box = over.getBoundingClientRect();
            place(tbody, rows, event.clientY < box.top + box.height / 2 ? over : group(over).pop().nextElementSibling);
        });

        tbody.addEventListener('drop', function (event) {
            if (dragging) { event.preventDefault(); }
        });

        tbody.addEventListener('dragend', function () {
            var tr = dragging;

            if (!tr) { return; }

            dragging = null;
            tr.draggable = false;
            group(tr).forEach(function (row) { row.classList.remove('opacity-50'); });
            moved(tbody, tr, onMove);
        });

        tbody.addEventListener('keydown', function (event) {
            var handle = event.target.closest && event.target.closest('[data-wi-row-drag]');
            var tr;
            var rows;
            var other;

            if (!handle || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) { return; }

            event.preventDefault();
            tr = head(handle.closest('tr'));
            rows = group(tr);

            if (event.key === 'ArrowUp') {
                other = head(tr.previousElementSibling);
                if (!other) { return; }
                place(tbody, rows, other);
            } else {
                other = rows[rows.length - 1].nextElementSibling;
                if (!other) { return; }
                place(tbody, rows, group(other).pop().nextElementSibling);
            }

            handle.focus();
            moved(tbody, tr, onMove);
        });
    }

    bind.handle = function () {
        var button = document.createElement('button');
        var icon = document.createElement('i');

        button.type = 'button';
        button.className = 'btn btn-sm btn-link text-body-secondary p-0 px-1';
        button.style.cursor = 'grab';
        button.setAttribute('data-wi-row-drag', '');
        button.setAttribute('aria-label', LABEL);
        button.title = LABEL;
        icon.className = 'bi bi-grip-vertical';
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);

        return button;
    };

    return bind;
})();
</script>
HTML;
    }
}
