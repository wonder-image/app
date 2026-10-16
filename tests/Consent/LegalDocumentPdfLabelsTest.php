<?php
/** php tests/Consent/LegalDocumentPdfLabelsTest.php */
declare(strict_types=1);

/**
 * Le etichette del PDF di un documento legale (tipo, versione, pubblicazione,
 * pagina) seguono il `language_code` del documento, non la lingua della
 * richiesta: la lingua corrente e le sue traduzioni tornano quelle di prima,
 * anche se la generazione fallisce, e una lingua assente o non registrata
 * ricade sulla lingua di default.
 *
 * Genera anche PDF veri (senza database: `info()` è uno stub) e ne legge il
 * testo dai content stream, per controllare i metadati della prima pagina e
 * la numerazione di tutte le pagine, ultima compresa (Footer() dell'ultima
 * pagina gira dentro Output(), dopo il ripristino della lingua).
 */

// FPDF segnala come deprecate le definizioni PHP dei font del core.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

use Wonder\Consent\LegalDocumentTypeContext;
use Wonder\Localization\LanguageContext;

$ROOT_APP = dirname(__DIR__, 2).'/app';
$SOCIETY = (object) ['legal_name' => 'Wonder Image'];

/** @var array<int, object> $LEGAL_DOCUMENTS */
$LEGAL_DOCUMENTS = [];

function info($table, $column, $value)
{
    global $LEGAL_DOCUMENTS;

    $row = $LEGAL_DOCUMENTS[(int) $value] ?? null;

    return $row === null ? (object) ['exists' => false] : clone $row;
}

require_once $ROOT_APP.'/function/helper.php';
require_once $ROOT_APP.'/function/string/common.php';
require_once $ROOT_APP.'/function/string/html.php';
require_once $ROOT_APP.'/function/string/pdf.php';
require_once $ROOT_APP.'/function/consent/documents.php';

$fail = 0;
function same(string $label, mixed $actual, mixed $expected): void
{
    global $fail;
    if ($actual === $expected) {
        echo "ok: $label\n";
    } else {
        $fail++;
        echo "FAIL: $label\n  expected: ".var_export($expected, true)."\n  actual:   ".var_export($actual, true)."\n";
    }
}

function contains(string $label, string $haystack, string $needle): void
{
    same($label, str_contains($haystack, $needle), true);
}

/**
 * Testo di ogni pagina, letto dai content stream FlateDecode di FPDF.
 *
 * @return list<string>
 */
function pdfPagesText(string $pdf): array
{
    preg_match_all('/\/Length (\d+)>>\nstream\n/', $pdf, $streams, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $pages = [];
    foreach ($streams as $stream) {
        $data = substr($pdf, $stream[0][1] + strlen($stream[0][0]), (int) $stream[1][0]);
        $content = @gzuncompress($data);
        if (!is_string($content) || !str_contains($content, ' Tj')) {
            continue;
        }

        preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', $content, $strings);
        $text = implode("\n", array_map(
            static fn (string $value): string => stripcslashes($value),
            $strings[1]
        ));

        $pages[] = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    return $pages;
}

LanguageContext::addLangPath(dirname(__DIR__, 2).'/resources/lang/');
LanguageContext::defaultLang('it')
    ::addLanguage('it', 'Italiano', 'https://example.test/', 'it')
    ::addLanguage('de', 'Deutsch', 'https://example.test/de/', 'de')
    ::addLanguage('en', 'English', 'https://example.test/en/', 'en');
LanguageContext::setLang('it');

LegalDocumentTypeContext::addType('privacy_policy', 'Privacy Policy');

// Risoluzione delle etichette.
same('it: etichette italiane', legalDocumentPdfLabels('it', 'privacy_policy'), [
    'type' => 'Informativa sulla privacy',
    'version' => 'Versione',
    'published' => 'Pubblicato',
    'page' => 'Pagina',
]);
same('de da una richiesta italiana: etichette tedesche', legalDocumentPdfLabels('de', 'privacy_policy'), [
    'type' => 'Datenschutzerklärung',
    'version' => 'Version',
    'published' => 'Veröffentlicht',
    'page' => 'Seite',
]);
same('codice maiuscolo normalizzato', legalDocumentPdfLabels(' DE ')['page'], 'Seite');
same('lingua corrente ripristinata', LanguageContext::getLang(), 'it');
same('traduzioni correnti ripristinate', __t('components.legal.pdf.page'), 'Pagina');

LanguageContext::setLang('en');
same('it da una richiesta inglese', legalDocumentPdfLabels('it')['version'], 'Versione');
same('lingua inglese ripristinata', LanguageContext::getLang(), 'en');
same('traduzioni inglesi ripristinate', __t('components.legal.pdf.published'), 'Published');

// Fallback: lingua non registrata (con file del core) e lingua assente.
same('fr non registrata: lingua di default', legalDocumentPdfLabels('fr')['page'], 'Pagina');
same('lingua assente: lingua di default', legalDocumentPdfLabels('')['version'], 'Versione');
same('fallback senza toccare la lingua corrente', LanguageContext::getLang(), 'en');

// Ripristino anche quando il callback lancia.
try {
    LanguageContext::withLang('de', static function (string $lang): never {
        throw new RuntimeException($lang);
    });
} catch (RuntimeException $exception) {
    same('withLang passa la lingua effettiva', $exception->getMessage(), 'de');
}
same('ripristino dopo un\'eccezione', LanguageContext::getLang(), 'en');
same('traduzioni ripristinate dopo un\'eccezione', __t('components.legal.pdf.page'), 'Page');

LanguageContext::setLang('it');

// PDF reali.
$paragraphs = [];
for ($i = 1; $i <= 40; $i++) {
    $paragraphs[] = ['type' => 'paragraph', 'data' => ['text' => "Absatz {$i}: ".str_repeat('Lorem ipsum dolor sit amet. ', 12)]];
}

$document = static fn (int $id, string $languageCode): object => (object) [
    'exists' => true,
    'id' => $id,
    'doc_type' => 'privacy_policy',
    'name' => 'Datenschutz',
    'checkbox_label' => '',
    'version' => '1.0.1',
    'language_code' => $languageCode,
    'published_at' => '2026-10-14 09:30:00',
    'content_snapshot' => json_encode(['blocks' => $paragraphs]),
];

$LEGAL_DOCUMENTS = [
    1 => $document(1, 'de'),
    2 => $document(2, 'it'),
    3 => $document(3, 'xx'),
    4 => $document(4, ''),
];

$generate = static function (int $id): array {
    $pdf = generateLegalDocumentPdf($id, ['output_mode' => 'S']);
    same("documento {$id}: PDF generato", $pdf->success, true);

    return [$pdf, pdfPagesText((string) $pdf->content)];
};

[$pdf, $pages] = $generate(1);
$out = getenv('LEGAL_PDF_OUT');
if (is_string($out) && $out !== '') {
    file_put_contents($out, $pdf->content);
}

same('de: almeno due pagine', count($pages) >= 2 && count($pages) === $pdf->page_count, true);
contains('de: metadati in prima pagina', $pages[0], 'Version 1.0.1 · DE · Veröffentlicht 14/10/2026 09:30');
contains('de: tipo documento in tedesco', $pages[0], 'DATENSCHUTZERKLÄRUNG');
foreach ($pages as $index => $text) {
    $number = $index + 1;
    contains("de: pagina {$number} numerata", $text, "Seite {$number}/".count($pages));
    same("de: pagina {$number} senza 'Pagina'", str_contains($text, 'Pagina'), false);
}
same('de: lingua della richiesta ripristinata', LanguageContext::getLang(), 'it');
same('de: traduzioni della richiesta ripristinate', __t('components.legal.pdf.version'), 'Versione');

[, $pages] = $generate(2);
contains('it: metadati in prima pagina', $pages[0], 'Versione 1.0.1 · IT · Pubblicato 14/10/2026 09:30');
contains('it: ultima pagina numerata', end($pages), 'Pagina '.count($pages).'/'.count($pages));

LanguageContext::setLang('de');
[, $pages] = $generate(3);
contains('xx non registrata, da richiesta tedesca: lingua di default', $pages[0], 'Versione 1.0.1 · XX · Pubblicato');
contains('xx: ultima pagina in lingua di default', end($pages), 'Pagina '.count($pages).'/');
same('xx: lingua tedesca ripristinata', LanguageContext::getLang(), 'de');

[, $pages] = $generate(4);
contains('lingua assente: lingua di default', $pages[0], 'Versione 1.0.1 · Pubblicato');

if ($fail > 0) {
    echo "\n$fail assertion(s) failed\n";
    exit(1);
}

echo "\nAll assertions passed\n";
