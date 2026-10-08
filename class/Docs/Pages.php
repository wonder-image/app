<?php

namespace Wonder\Docs;

use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;
use Wonder\Elements\Components\Code;
use Wonder\Elements\Components\Preview;
use Wonder\Elements\Components\Text;

/**
 * Gli aiuti che le viste del catalogo usano per non ripetersi: testo con
 * `codice` inline, badge di disponibilità, anteprima e blocco di codice di un
 * esempio, selettore globale del tema.
 */
final class Pages
{
    public const GROUP = 'docs';

    /** Testo escapato; le parti fra apici inversi diventano `<code>`, le righe vuote separano i paragrafi. */
    public static function text(string $text, bool $paragraphs = false): string
    {
        $escaped = htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);

        if (!$paragraphs) {
            return $escaped;
        }

        $blocks = preg_split('/\n\s*\n/', $escaped) ?: [];

        return implode('', array_map(
            static fn (string $block): string => '<p>'.nl2br(trim($block)).'</p>',
            array_filter($blocks, static fn (string $block): bool => trim($block) !== '')
        ));
    }

    /**
     * I badge "Wonder" e "Bootstrap": pieni dove il renderer c'è, spenti con
     * il motivo nel `title` dove manca.
     *
     * @param array<string, ThemeAvailability> $availability
     */
    public static function badges(ComponentDoc $doc, array $availability, string $theme = 'bootstrap'): string
    {
        $html = '';

        foreach ($availability as $key => $support) {
            $note = trim($doc->getNote($key));
            $title = $support->available
                ? trim(($support->inherited ? $support->reason.' ' : '').$note)
                : trim($support->reason.' '.$note);

            $badge = Badge::make($support->label())
                ->variant($support->available ? ($support->inherited ? 'info' : 'success') : 'secondary')
                ->pill()
                ->icon($support->available ? 'bi bi-check2' : 'bi bi-dash');

            if (!$support->available) {
                $badge->outline();
            }

            if ($title !== '') {
                $badge->title($title);
            }

            $html .= $badge->render($theme).' ';
        }

        return trim($html);
    }

    /** La cella del tema nella tabella del catalogo. */
    public static function availabilityCell(ThemeAvailability $support, string $note = ''): string
    {
        $title = trim(($support->available ? $support->reason : $support->reason).' '.$note);
        $attribute = $title !== '' ? ' title="'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"' : '';

        if (!$support->available) {
            return '<span class="text-body-tertiary"'.$attribute.'><i class="bi bi-dash-lg" aria-hidden="true"></i><span class="visually-hidden">No</span></span>';
        }

        $color = $support->inherited ? 'text-info' : 'text-success';

        return '<span class="'.$color.'"'.$attribute.'><i class="bi bi-check-lg" aria-hidden="true"></i><span class="visually-hidden">Sì</span></span>'
            .($support->inherited ? ' <small class="text-body-secondary">ereditato</small>' : '');
    }

    /**
     * L'anteprima di un esempio: una sorgente per tema, spenta dove il
     * componente o l'esempio non lo supportano.
     *
     * @param array<string, ThemeAvailability> $availability
     */
    public static function preview(ComponentDoc $doc, int $index, Example $example, array $availability, Urls $urls): Preview
    {
        $preview = Preview::make($example->getTitle())
            ->group(self::GROUP)
            ->height($example->getHeight() > 0 ? $example->getHeight() : 96);

        foreach ($availability as $theme => $support) {
            $reason = '';

            if (!$support->available) {
                $reason = $support->reason;
            } elseif (!$example->supports($theme)) {
                $reason = 'Questo esempio usa un\'API disponibile solo in '.implode(' e ', array_map([ThemeSupport::class, 'label'], $example->getThemes())).'.';
            }

            $preview->source(
                $theme,
                $support->label(),
                $reason === '' ? $urls->preview($doc->getSlug(), $index, $theme) : null,
                [
                    'schemes' => $theme === 'bootstrap',
                    'reason' => $reason,
                ]
            );
        }

        return $preview;
    }

    public static function code(string $code, string $title = 'PHP'): Code
    {
        return Code::make($code, 'php')->title($title);
    }

    /**
     * Il selettore globale: tema delle anteprime e schema chiaro/scuro,
     * sincronizzati sul gruppo. Due `ButtonGroup` di `Button` con gli
     * attributi `data-wi-preview-switch` letti dallo script della `Preview`.
     */
    public static function themeSwitch(array $themes): string
    {
        $buttons = [];

        foreach ($themes as $theme) {
            $buttons[] = Button::make(ThemeSupport::label($theme))
                ->variant('secondary')
                ->outline()
                ->size('sm')
                ->attr('data-wi-preview-switch', self::GROUP)
                ->attr('data-wi-preview-set-source', $theme)
                ->attr('data-wi-docs-theme', $theme);
        }

        $schemes = [
            Button::make('Chiaro')->variant('secondary')->outline()->size('sm')->icon('bi bi-sun-fill', 'start')
                ->title('Chiaro')
                ->attr('data-wi-preview-switch', self::GROUP)
                ->attr('data-wi-preview-set-scheme', 'light')
                ->attr('data-wi-docs-scheme', 'light'),
            Button::make('Scuro')->variant('secondary')->outline()->size('sm')->icon('bi bi-moon-stars-fill', 'start')
                ->title('Scuro')
                ->attr('data-wi-preview-switch', self::GROUP)
                ->attr('data-wi-preview-set-scheme', 'dark')
                ->attr('data-wi-docs-scheme', 'dark'),
        ];

        return '<div class="wi-docs-switch d-flex flex-wrap align-items-center gap-2" data-wi-docs-switch>'
            .Text::make('Anteprime:')->tag('span')->small()->muted()->render('bootstrap')
            .ButtonGroup::make($buttons)->label('Tema delle anteprime')->render('bootstrap')
            .ButtonGroup::make($schemes)->label('Schema chiaro o scuro (solo Bootstrap)')->render('bootstrap')
            .'</div>';
    }

    /** Lo script che evidenzia nel selettore la scelta corrente (letta da `localStorage`). */
    public static function themeSwitchScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    var group = 'docs';
    var read = function (key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } };
    var paint = function () {
        var theme = read('wi-preview:' + group + ':source') || 'bootstrap';
        var scheme = read('wi-preview:' + group + ':scheme') || 'light';
        document.querySelectorAll('[data-wi-docs-theme]').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-wi-docs-theme') === theme);
        });
        document.querySelectorAll('[data-wi-docs-scheme]').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-wi-docs-scheme') === scheme);
        });
    };
    document.addEventListener('wi-preview:switch', paint);
    document.addEventListener('DOMContentLoaded', paint);
    paint();
})();
</script>
HTML;
    }

    /** Il filtro della barra laterale: nasconde le voci che non contengono il testo. */
    public static function sidebarScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    var input = document.querySelector('[data-wi-docs-filter]');
    var list = document.querySelector('[data-wi-docs-sidebar]');
    if (!input || !list) { return; }
    input.addEventListener('input', function () {
        var needle = input.value.trim().toLowerCase();
        list.querySelectorAll('[data-wi-docs-item]').forEach(function (item) {
            var haystack = (item.getAttribute('data-wi-docs-item') || '').toLowerCase();
            item.hidden = needle !== '' && haystack.indexOf(needle) === -1;
        });
        list.querySelectorAll('[data-wi-docs-section]').forEach(function (section) {
            var visible = section.querySelectorAll('[data-wi-docs-item]:not([hidden])').length;
            section.hidden = visible === 0;
        });
    });
})();
</script>
HTML;
    }
}
