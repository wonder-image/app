<?php
/** php tests/App/Mail/MailLayoutTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/string/common.php';
require __DIR__ . '/../../../app/function/string/html.php';
require __DIR__ . '/../../../app/function/string/sanitize.php';
require __DIR__ . '/../../../app/function/mail.php';

use Wonder\App\Support\Mail\MailLayout;
use Wonder\App\Support\Mail\MailMessage;

$GLOBALS['SOCIETY'] = (object) [
    'name' => 'Bottega Rossi',
    'legal_name' => 'Bottega Rossi S.r.l.',
    'pi' => '01234567890',
    'cf' => '01234567890',
    'email' => 'info@bottega.test',
    'tel' => '+39 02 1234567',
    'address' => 'Via Roma 1, 20100 Milano (MI)',
    'legal_street' => '',
];

MailLayout::useBrand('#c2410c', '#ffffff');

$built = MailMessage::make()
    ->preheader('Il link vale 30 minuti')
    ->heading('Recupero password')
    ->text("Ciao <b>Mario</b> & co.\nseconda riga")
    ->button('Modifica password', 'https://bottega.test/reset?token=a&b=1')
    ->details(['Account' => 'mario@example.test', 'Ordine' => '#12'])
    ->small('Se non sei stato tu, ignora questa mail.');

$html = emailTemplate($built);
$text = htmlToText($html);

check('il builder escapa il testo e il layout non lo decodifica', static fn (): bool =>
    str_contains($html, 'Ciao &lt;b&gt;Mario&lt;/b&gt; &amp; co.<br>')
    && !str_contains($html, '<b>Mario</b>')
);

check('il marker non arriva nella mail e il preheader sta subito dopo il body', static fn (): bool =>
    !str_contains($html, MailMessage::MARKER)
    && preg_match('#<body[^>]*><div[^>]*display:none[^>]*>Il link vale 30 minuti#', $html) === 1
    && substr_count($html, 'Il link vale 30 minuti') === 1
);

check('pulsante con il colore primario e link di riserva', static fn (): bool =>
    str_contains($html, 'background-color:#c2410c')
    && str_contains($html, 'href="https://bottega.test/reset?token=a&amp;b=1"')
);

check('un URL non sicuro diventa testo, senza link', static function (): bool {
    $body = (string) MailMessage::make()->button('Apri', 'javascript:alert(1)');

    return !str_contains($body, 'javascript:') && str_contains($body, '<p style="margin:0 0 16px;">Apri</p>');
});

check('versione testo: niente preheader né link di riserva, dettagli come "Etichetta: valore"', static fn (): bool =>
    !str_contains($text, 'Il link vale 30 minuti')
    && !str_contains($text, 'Se il pulsante non funziona')
    && str_contains($text, 'Account: mario@example.test')
    && str_contains($text, 'Recupero password')
    && str_contains($text, 'Modifica password')
);

check('piè di pagina con ragione sociale, dati fiscali, contatti e diritti', static fn (): bool =>
    str_contains($html, 'Bottega Rossi S.r.l.')
    && str_contains($html, 'P.IVA e C.F. 01234567890')
    && str_contains($html, 'Via Roma 1, 20100 Milano (MI)')
    && str_contains($html, 'mailto:info@bottega.test')
    && str_contains($html, '&copy; '.date('Y').' Bottega Rossi S.r.l. Tutti i diritti riservati.')
);

check('un indirizzo composto da parti vuote non compare', static function (): bool {
    $previous = $GLOBALS['SOCIETY'];
    $GLOBALS['SOCIETY'] = (object) ['name' => 'Solo Nome', 'address' => ' ,  ()'];
    $html = emailTemplate('<p>x</p>');
    $GLOBALS['SOCIETY'] = $previous;

    return !str_contains($html, ', ()') && str_contains($html, 'Solo Nome');
});

check('tema scuro dichiarato', static fn (): bool =>
    str_contains($html, '<meta name="color-scheme" content="light dark">')
    && str_contains($html, 'prefers-color-scheme:dark')
);

// Corpi scritti a mano: passano ancora da sanitizeEcho() come prima.

check('corpo vecchio: barre e entità decodificate come prima', static function (): bool {
    $html = emailTemplate("L\\'ordine &egrave; pronto<br><a href='https://bottega.test'>Apri</a>");

    return str_contains($html, "L'ordine è pronto<br><a href='https://bottega.test'>Apri</a>");
});

check('corpo vecchio del modulo form ("Ecco i dettagli")', static function (): bool {
    $body = 'Ecco i dettagli:<br>Nome: <b>Mario</b><br>Email: <b>mario@example.test</b>';
    $html = emailTemplate($body);

    return str_contains($html, $body) && str_contains(htmlToText($html), 'Nome: Mario');
});

check('corpo vecchio con righe rsvp "etichetta: <strong>valore</strong>"', static function (): bool {
    $body = 'Ospiti: <strong>2</strong><br>Menu: <strong>vegetariano</strong>';

    return str_contains(emailTemplate($body), $body);
});

check('corpo protetto dal gestionale (Mailer::shield) torna leggibile', static function (): bool {
    // Uscita di shield() per "Caffè & più \ fine": entità doppie, barre raddoppiate.
    $body = 'Caff&amp;#232; &amp;#38; pi&amp;#249; \\\\ fine';
    $html = emailTemplate($body);

    // Come prima: sanitizeEcho() toglie un livello di barre e di &amp;.
    return str_contains($html, 'Caff&#232; &#38; pi&#249; \\ fine');
});

check('MailMessage passa da sendMail come stringa', static fn (): bool =>
    sendMail('a@example.test', 'b@example.test', 'Oggetto', MailMessage::make()->text('Ciao')) === true
);

check('template sconosciuto: stringa vuota', static fn (): bool => emailTemplate('<p>x</p>', 'altro') === '');

check('un primario troppo chiaro torna al colore di riserva', static function (): bool {
    MailLayout::useBrand('#fefefe', '#000000');
    $brand = MailLayout::brand();
    MailLayout::useBrand(null);

    return $brand['primary'] === '#18181b';
});

check('il testo del pulsante resta leggibile su un primario scuro', static function (): bool {
    MailLayout::useBrand('#1e3a8a', '#1e3a8a');
    $brand = MailLayout::brand();
    MailLayout::useBrand(null);

    return $brand['contrast'] !== '#1e3a8a';
});

check('un path relativo resta un link (__r() senza APP_URL)', static function (): bool {
    $body = (string) MailMessage::make()->button('Apri', '/backend/account/password/restore/?token=x');
    $protocolRelative = (string) MailMessage::make()->button('Apri', '//evil.test/x');

    return str_contains($body, 'href="/backend/account/password/restore/?token=x"')
        && !str_contains($protocolRelative, 'href=');
});

check('un corpo salvato in coda come JSON resta un corpo del builder', static function () use ($built): bool {
    $payload = json_decode((string) json_encode(['body' => (string) $built]), true);
    $html = emailTemplate($payload['body']);

    return MailMessage::isBuilt($payload['body'])
        && str_contains($html, 'Ciao &lt;b&gt;Mario&lt;/b&gt; &amp; co.<br>');
});

check('un corpo scritto a mano resta in tema chiaro, quello del builder no', static function () use ($built): bool {
    $legacy = emailTemplate('<p style="color:#000">Testo vecchio</p>');
    $new = emailTemplate((string) $built);

    return str_contains($legacy, '<meta name="color-scheme" content="light">')
        && !str_contains($legacy, 'prefers-color-scheme:dark')
        && str_contains($new, '<meta name="color-scheme" content="light dark">')
        && str_contains($new, 'prefers-color-scheme:dark');
});

check('senza logo il nome ha il quadratino del brand, con il logo no', static function (): bool {
    unset($GLOBALS['PATH']);
    $without = emailTemplate((string) MailMessage::make()->text('x'));
    $GLOBALS['PATH'] = (object) ['logo' => 'https://cdn.test/logo.png'];
    $with = emailTemplate((string) MailMessage::make()->text('x'));
    unset($GLOBALS['PATH']);

    return str_contains($without, 'class="wi-mail-mark"')
        && !str_contains($with, 'class="wi-mail-mark"')
        && str_contains($with, 'src="https://cdn.test/logo.png"');
});

check('un logo SVG non va nella mail: resta il nome', static function (): bool {
    $GLOBALS['PATH'] = (object) ['logo' => 'https://cdn.test/logo.svg?v=2'];
    $html = emailTemplate((string) MailMessage::make()->text('x'));
    unset($GLOBALS['PATH']);

    return !str_contains($html, 'logo.svg') && str_contains($html, 'class="wi-mail-mark"');
});

$order = MailMessage::make()->items([
    ['name' => 'Tazza <b>blu</b>', 'description' => 'Blu, 350 ml', 'image' => 'https://cdn.test/tazza.jpg',
     'quantity' => 2, 'price' => '€ 10,00', 'original_price' => '€ 12,00', 'total' => '€ 20,00'],
    ['name' => 'Piattino', 'image' => 'javascript:alert(1)', 'quantity' => 1, 'price' => '€ 5,00', 'total' => '€ 5,00'],
    ['name' => ''],
], ['Subtotale' => '€ 25,00', 'Spedizione' => 'Gratis', 'Totale' => '€ 25,00'], 'Il tuo ordine');
$orderHtml = emailTemplate((string) $order);
$orderText = htmlToText($orderHtml);

check('items: righe escapate, prezzo pieno barrato, immagini solo http(s)', static fn (): bool =>
    str_contains($orderHtml, 'Tazza &lt;b&gt;blu&lt;/b&gt;')
    && str_contains($orderHtml, 'text-decoration:line-through;">€ 12,00</span>')
    && str_contains($orderHtml, 'src="https://cdn.test/tazza.jpg"')
    && !str_contains($orderHtml, 'javascript:')
    && str_contains($orderHtml, 'wi-mail-item-placeholder')
    && substr_count($orderHtml, 'class="wi-mail-item-row" width="64"') === 2
);

check('items: il totale finale è in evidenza e il testo si legge', static fn (): bool =>
    str_contains($orderHtml, 'wi-mail-heading wi-mail-total')
    && str_contains($orderText, 'Quantità: 2 · € 10,00')
    && !str_contains($orderText, '€ 12,00')
    && str_contains($orderText, 'Totale: € 25,00')
    && str_contains($orderText, 'Spedizione: Gratis')
);

check('items senza immagini non ha la colonna anteprima; vuoto non stampa nulla', static function (): bool {
    $plain = (string) MailMessage::make()->items([['name' => 'Servizio', 'total' => '€ 1,00']]);
    $empty = (string) MailMessage::make()->items([]);

    return !str_contains($plain, 'width="64"') && str_contains($plain, 'Servizio')
        && $empty === (string) MailMessage::make();
});

check('table: intestazione, colonne a destra, celle escapate', static function (): bool {
    $html = (string) MailMessage::make()->table(['Scadenza', 'Importo'], [['31/12', '€ 1.<i>0</i>']], 'Rate', [1]);

    return str_contains($html, '>Scadenza</th>')
        && str_contains($html, 'align="right"')
        && str_contains($html, '€ 1.&lt;i&gt;0&lt;/i&gt;')
        && (string) MailMessage::make()->table(['A'], []) === (string) MailMessage::make();
});

summary();
