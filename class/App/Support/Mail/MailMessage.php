<?php

namespace Wonder\App\Support\Mail;

/**
 * Corpo di una mail composto a blocchi, da passare a `sendMail()` /
 * `queueMail()` al posto di una stringa HTML scritta a mano.
 *
 *     $body = MailMessage::make()
 *         ->preheader('Il link vale 30 minuti')
 *         ->heading('Recupero password')
 *         ->text('Ecco il link per modificare la tua password.')
 *         ->button('Modifica password', $link)
 *         ->details(['Account' => $email]);
 *
 *     sendMail($from, $to, 'Recupero password', $body);
 *
 * Ogni testo viene escapato; `html()` è l'unico blocco che accetta markup
 * scritto dal codice. Il risultato è solo il contenuto: logo, colori,
 * piè di pagina e versione testo li aggiunge `MailLayout` all'invio.
 */
final class MailMessage implements \Stringable
{

    /** Prima riga del corpo: dice a `MailLayout` che il markup è già sicuro. */
    public const MARKER = '<!--wi-mail:v1-->';

    /** Elementi da tenere fuori dalla versione testo (`htmlToText()`). */
    public const TEXT_SKIP = 'data-wi-mail-text-skip';

    /** Cella etichetta dei dettagli: nel testo diventa "Etichetta: valore". */
    public const LABEL = 'data-wi-mail-label';

    private const NOTICE_COLORS = [
        'info' => ['#eff6ff', '#1d4ed8', '#bfdbfe'],
        'success' => ['#f0fdf4', '#15803d', '#bbf7d0'],
        'warning' => ['#fffbeb', '#b45309', '#fde68a'],
        'danger' => ['#fef2f2', '#b91c1c', '#fecaca'],
    ];

    private string $preheader = '';

    /** @var list<string> */
    private array $blocks = [];

    public static function make(): self
    {
        return new self();
    }

    /** Anteprima mostrata accanto all'oggetto nella lista della casella. */
    public function preheader(string $text): self
    {
        $this->preheader = trim($text);

        return $this;
    }

    public function heading(string $text): self
    {
        return $this->block(
            '<h1 class="wi-mail-heading" style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;">'
            .self::escape($text).'</h1>'
        );
    }

    /** Paragrafo di testo; gli a capo diventano `<br>`. */
    public function text(string $text): self
    {
        return $this->block(
            '<p style="margin:0 0 16px;">'.self::lines($text).'</p>'
        );
    }

    /** Markup scritto dal codice, mai dati di chi usa il sito senza escape. */
    public function html(string $html): self
    {
        return $this->block('<div style="margin:0 0 16px;">'.$html.'</div>');
    }

    /**
     * Pulsante con il colore primario del sito. Con `$fallback` aggiunge
     * sotto il link in chiaro, per i client che non aprono il pulsante.
     * Un URL che non sia http(s), mailto, tel o un path `/...` diventa testo
     * semplice.
     */
    public function button(string $label, string $url, bool $fallback = true): self
    {
        $url = trim($url);

        if (!self::safeUrl($url)) {
            return $this->text($label);
        }

        $brand = MailLayout::brand();
        $href = self::escape($url);
        $label = self::escape($label);

        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;border-collapse:separate;">'
            .'<tr><td class="wi-mail-button" bgcolor="'.$brand['primary'].'" style="border-radius:8px;background-color:'.$brand['primary'].';">'
            .'<a class="wi-mail-button-link" href="'.$href.'" target="_blank" rel="noopener" style="display:inline-block;padding:14px 26px;font-size:15px;line-height:20px;font-weight:600;color:'.$brand['contrast'].';text-decoration:none;border-radius:8px;">'
            .$label.'</a></td></tr></table>';

        if ($fallback) {
            $html .= '<p class="wi-mail-muted" '.self::TEXT_SKIP.' style="margin:0 0 16px;font-size:12px;line-height:1.5;color:#71717a;">'
                .self::escape(MailLayout::translate('emails.layout.button_fallback', 'Se il pulsante non funziona, copia questo link nel browser:'))
                .'<br><a href="'.$href.'" target="_blank" rel="noopener" style="color:#71717a;word-break:break-all;">'.$href.'</a></p>';
        }

        return $this->block($html);
    }

    /**
     * Elenco etichetta/valore (riepilogo di un ordine, dati di un form).
     *
     * @param array<string, scalar|null> $rows
     */
    public function details(array $rows, ?string $title = null): self
    {
        if ($rows === []) {
            return $this;
        }

        $html = self::title($title).'<table role="presentation" class="wi-mail-detail" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:separate;background-color:#f4f4f5;border-radius:8px;">';

        $last = count($rows) - 1;
        $index = 0;

        foreach ($rows as $label => $value) {
            $border = $index < $last ? 'border-bottom:1px solid #e4e4e7;' : '';
            $html .= '<tr>'
                .'<td '.self::LABEL.' class="wi-mail-muted wi-mail-detail-row" valign="top" style="padding:12px 16px;'.$border.'width:38%;font-size:14px;color:#71717a;">'.self::escape((string) $label).'</td>'
                .'<td class="wi-mail-detail-row" valign="top" style="padding:12px 16px;'.$border.'font-size:14px;font-weight:600;color:#18181b;word-break:break-word;">'.self::lines((string) $value).'</td>'
                .'</tr>';
            $index++;
        }

        return $this->block($html.'</table>');
    }

    /**
     * Righe di un ordine o di un preventivo: anteprima, nome, descrizione
     * (variante, taglia), quantità per prezzo e totale di riga. Con
     * `original_price` il prezzo pieno compare barrato accanto a quello
     * scontato. I prezzi arrivano già formattati da chi chiama (valuta,
     * decimali): il blocco non fa conti. Sotto, i `$totals` (subtotale,
     * spedizione, sconto, ...): l'ultimo è il totale e va in evidenza.
     *
     *     ->items([
     *         ['name' => 'Tazza', 'description' => 'Blu, 350 ml', 'image' => $url,
     *          'quantity' => 2, 'price' => '€ 10,00', 'original_price' => '€ 12,00',
     *          'total' => '€ 20,00'],
     *     ], ['Subtotale' => '€ 20,00', 'Spedizione' => 'Gratis', 'Totale' => '€ 20,00'])
     *
     * @param list<array{name: string, description?: ?string, image?: ?string, url?: ?string, quantity?: int|float|string|null, price?: ?string, original_price?: ?string, total?: ?string}> $items
     * @param array<string, scalar|null> $totals
     */
    public function items(array $items, array $totals = [], ?string $title = null): self
    {
        $items = array_values(array_filter($items, static fn ($item): bool => is_array($item) && trim((string) ($item['name'] ?? '')) !== ''));

        if ($items === [] && $totals === []) {
            return $this;
        }

        $html = self::title($title);
        $images = false;

        foreach ($items as $item) {
            if (self::imageUrl($item['image'] ?? null) !== null) {
                $images = true;
                break;
            }
        }

        if ($items !== []) {
            $html .= '<table role="presentation" class="wi-mail-items" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 '.($totals === [] ? '24' : '0').'px;border-top:1px solid #e4e4e7;">';
            $quantityLabel = MailLayout::translate('emails.layout.quantity', 'Quantità');

            foreach ($items as $item) {
                $border = 'border-bottom:1px solid #e4e4e7;';
                $html .= '<tr>';

                if ($images) {
                    $image = self::imageUrl($item['image'] ?? null);
                    $html .= '<td class="wi-mail-item-row" width="64" valign="top" style="width:64px;padding:16px 0;'.$border.'">'
                        .($image === null
                            ? '<div class="wi-mail-item-placeholder" style="width:64px;height:64px;border-radius:8px;background-color:#f4f4f5;"></div>'
                            : '<img src="'.self::escape($image).'" alt="" width="64" height="64" style="display:block;width:64px;height:64px;object-fit:cover;border-radius:8px;border:0;">')
                        .'</td>';
                }

                $name = self::escape(trim((string) $item['name']));
                $url = trim((string) ($item['url'] ?? ''));

                if ($url !== '' && self::safeUrl($url)) {
                    $name = '<a href="'.self::escape($url).'" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">'.$name.'</a>';
                }

                $lines = ['<span class="wi-mail-heading" style="font-size:15px;font-weight:600;color:#18181b;">'.$name.'</span>'];
                $description = trim((string) ($item['description'] ?? ''));

                if ($description !== '') {
                    $lines[] = '<span class="wi-mail-muted" style="font-size:13px;color:#71717a;">'.self::lines($description).'</span>';
                }

                $quantity = trim((string) ($item['quantity'] ?? ''));
                $price = trim((string) ($item['price'] ?? ''));
                $original = trim((string) ($item['original_price'] ?? ''));
                $meta = [];

                if ($quantity !== '') {
                    $meta[] = self::escape($quantityLabel).': '.self::escape($quantity);
                }

                if ($price !== '') {
                    $priceHtml = '';

                    if ($original !== '' && $original !== $price) {
                        $priceHtml .= '<span '.self::TEXT_SKIP.' class="wi-mail-muted" style="color:#a1a1aa;text-decoration:line-through;">'.self::escape($original).'</span> ';
                        $priceHtml .= '<span class="wi-mail-sale" style="color:#b91c1c;font-weight:600;">'.self::escape($price).'</span>';
                    } else {
                        $priceHtml .= self::escape($price);
                    }

                    $meta[] = $priceHtml;
                }

                if ($meta !== []) {
                    $lines[] = '<span class="wi-mail-muted" style="font-size:13px;color:#71717a;">'.implode(' &middot; ', $meta).'</span>';
                }

                $total = trim((string) ($item['total'] ?? ''));

                $html .= '<td class="wi-mail-item-row" valign="top" style="padding:16px 12px 16px '.($images ? '14' : '0').'px;'.$border.'line-height:1.5;">'.implode('<br>', $lines).'</td>'
                    .'<td class="wi-mail-item-row wi-mail-heading" valign="top" align="right" style="padding:16px 0;'.$border.'font-size:15px;font-weight:600;color:#18181b;white-space:nowrap;">'.self::escape($total).'</td>'
                    .'</tr>';
            }

            $html .= '</table>';
        }

        if ($totals !== []) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;">';
            $last = count($totals) - 1;
            $index = 0;

            foreach ($totals as $label => $value) {
                $grand = $index === $last && $index > 0;
                $top = $grand ? 'border-top:1px solid #e4e4e7;padding-top:12px;' : 'padding-top:'.($index === 0 ? '14' : '4').'px;';
                $size = $grand ? 'font-size:17px;font-weight:700;color:#18181b;' : 'font-size:14px;color:#71717a;';
                $class = $grand ? 'wi-mail-heading wi-mail-total' : 'wi-mail-muted';

                $html .= '<tr>'
                    .'<td '.self::LABEL.' class="'.$class.'" align="right" style="'.$top.'padding-bottom:4px;'.$size.'">'.self::escape((string) $label).'</td>'
                    .'<td class="'.$class.'" align="right" width="120" style="width:120px;'.$top.'padding-bottom:4px;padding-left:16px;white-space:nowrap;'.$size.($grand ? '' : 'color:#18181b;').'">'.self::escape((string) $value).'</td>'
                    .'</tr>';
                $index++;
            }

            $html .= '</table>';
        }

        return $this->block($html);
    }

    /**
     * Tabella semplice con intestazione (scadenze, appuntamenti, righe di un
     * estratto conto). Le colonne il cui nome è in `$alignRight` vanno a
     * destra, utile per importi e quantità.
     *
     * @param list<string> $columns
     * @param list<list<scalar|null>> $rows
     * @param list<int> $alignRight indici delle colonne allineate a destra
     */
    public function table(array $columns, array $rows, ?string $title = null, array $alignRight = []): self
    {
        if ($rows === []) {
            return $this;
        }

        $count = max(count($columns), ...array_map(static fn ($row): int => is_array($row) ? count($row) : 0, $rows));
        $align = static fn (int $index): string => in_array($index, $alignRight, true) ? 'right' : 'left';

        $html = self::title($title)
            .'<table role="presentation" class="wi-mail-detail" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:separate;background-color:#f4f4f5;border-radius:8px;">';

        if ($columns !== []) {
            $html .= '<tr>';

            for ($i = 0; $i < $count; $i++) {
                $html .= '<th class="wi-mail-muted wi-mail-detail-row" align="'.$align($i).'" style="padding:10px 16px;border-bottom:1px solid #e4e4e7;font-size:12px;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#71717a;text-align:'.$align($i).';">'
                    .self::escape((string) ($columns[$i] ?? '')).'</th>';
            }

            $html .= '</tr>';
        }

        $last = count($rows) - 1;

        foreach (array_values($rows) as $index => $row) {
            $row = array_values(is_array($row) ? $row : [$row]);
            $border = $index < $last ? 'border-bottom:1px solid #e4e4e7;' : '';
            $html .= '<tr>';

            for ($i = 0; $i < $count; $i++) {
                $html .= '<td class="wi-mail-detail-row" align="'.$align($i).'" valign="top" style="padding:10px 16px;'.$border.'font-size:14px;color:#18181b;text-align:'.$align($i).';word-break:break-word;">'
                    .self::lines((string) ($row[$i] ?? '')).'</td>';
            }

            $html .= '</tr>';
        }

        return $this->block($html.'</table>');
    }

    /** Riquadro evidenziato: `info`, `success`, `warning`, `danger`. */
    public function notice(string $text, string $level = 'info'): self
    {
        [$background, $color, $border] = self::NOTICE_COLORS[$level] ?? self::NOTICE_COLORS['info'];

        return $this->block(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:separate;">'
            .'<tr><td class="wi-mail-notice" style="padding:14px 16px;border-radius:8px;border:1px solid '.$border.';background-color:'.$background.';color:'.$color.';font-size:14px;line-height:1.5;">'
            .self::lines($text).'</td></tr></table>'
        );
    }

    public function divider(): self
    {
        return $this->block(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:8px 0 24px;"><tr>'
            .'<td class="wi-mail-rule" style="height:1px;line-height:1px;font-size:1px;background-color:#e4e4e7;">&nbsp;</td></tr></table>'
        );
    }

    /** Nota in piccolo, per esempio "Se non sei stato tu, ignora questa mail". */
    public function small(string $text): self
    {
        return $this->block(
            '<p class="wi-mail-muted" style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#71717a;">'
            .self::lines($text).'</p>'
        );
    }

    public function render(): string
    {
        $preheader = $this->preheader === ''
            ? ''
            : '<div '.self::TEXT_SKIP.' data-wi-mail-preheader>'.self::escape($this->preheader).'</div>';

        return self::MARKER.$preheader.implode('', $this->blocks);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function isBuilt(string $body): bool
    {
        return str_starts_with($body, self::MARKER);
    }

    private function block(string $html): self
    {
        $this->blocks[] = $html;

        return $this;
    }

    private static function title(?string $title): string
    {
        if ($title === null || trim($title) === '') {
            return '';
        }

        return '<p style="margin:0 0 8px;font-size:13px;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#71717a;" class="wi-mail-muted">'
            .self::escape($title).'</p>';
    }

    /** Solo http(s) o un path `/...`: niente `data:` o `javascript:` nelle anteprime. */
    private static function imageUrl(mixed $url): ?string
    {
        $url = is_string($url) ? trim($url) : '';

        return $url !== '' && preg_match('#^(https?://|/(?![/\\\\]))#i', $url) === 1 ? $url : null;
    }

    private static function safeUrl(string $url): bool
    {
        // Anche `/percorso`: senza APP_URL `__r()` restituisce un path relativo,
        // e il vecchio corpo scritto a mano teneva comunque il link.
        return $url !== '' && preg_match('#^(https?://|mailto:|tel:|/(?![/\\\\]))#i', $url) === 1;
    }

    /** Testo escapato con `<br>` al posto degli a capo (senza lasciare il `\n`). */
    private static function lines(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], '<br>', self::escape($value));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
