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

        $html = '';

        if ($title !== null && trim($title) !== '') {
            $html .= '<p style="margin:0 0 8px;font-size:13px;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#71717a;" class="wi-mail-muted">'
                .self::escape($title).'</p>';
        }

        $html .= '<table role="presentation" class="wi-mail-detail" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 24px;border-collapse:separate;background-color:#f4f4f5;border-radius:8px;">';

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
