<?php
/** php tests/Backend/Pages/CardPagesTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../../harness.php';
require __DIR__.'/../../../app/function/backend/components/accordion.php';

use Wonder\App\LegacyGlobals;
use Wonder\App\Theme;

/**
 * Le pagine backend passate da <wi-card> a Card: il markup esce dal server
 * uguale a quello che createCard() della lib costruiva nel browser.
 */
$root = sys_get_temp_dir().'/card-pages-'.uniqid();
@mkdir($root.'/custom/view/layout/backend', 0777, true);
// Layout finto: stampa solo il contenuto della pagina.
foreach (['show', 'list', 'form', 'main'] as $layout) {
    file_put_contents($root.'/custom/view/layout/backend/'.$layout.'.php', '<?php echo $PAGE_CONTENT;');
}
// Il backend gira col tema bootstrap, come in runtime.
Theme::set('bootstrap');
LegacyGlobals::share(['ROOT' => $root, 'ROOT_APP' => dirname(__DIR__, 3).'/app']);

function infoUser($id): object { return (object) ['exists' => $id !== '', 'fullName' => 'Mario <Rossi>']; }
function infoLegalDocument($id): object {
    return (object) ['id' => 7, 'name' => 'Privacy', 'renderName' => 'Privacy', 'doc_type' => 'privacy', 'version' => '2',
        'language_code' => 'it', 'published_at' => '2026-10-01', 'active' => 'true',
        'renderLabel' => 'Accetto la <b>privacy</b>', 'renderContent' => '<p>Testo</p>'];
}
function consentEventAction(string $a): object { return (object) ['badge' => '<span class="badge">'.$a.'</span>']; }
function consentEventSource(string $s): object { return (object) ['text' => $s]; }
function active($v, $id): object { return (object) ['badge' => '<span class="badge">attivo</span>']; }
function __r(string $name, array $params = []): string { return '/backend/'.$name.'/'.($params['id'] ?? ''); }

function mailService(string $s): object { return (object) ['text' => 'smtp']; }
function mailLogStatus(string $s): object { return (object) ['badge' => '<span class="badge">inviata</span>']; }

final class FakeLegalResource { public static function slug(): string { return 'legal-documents'; } }

$page = static function (string $file, array $vars): string {
    extract($vars);
    ob_start();
    include dirname(__DIR__, 3).'/app/view/pages/backend/'.$file;

    return (string) ob_get_clean();
};

$card = '<div class="card border"><div class="card-body row g-3">';

$authUsers = $page('log/auth-users/show.php', ['ITEM' => [
    'user_id' => 3, 'event' => 'login <ok>', 'area' => 'backend', 'ip' => '127.0.0.1',
    'user_agent' => 'curl', 'meta' => '{"a":1}',
]]);

check('log/auth-users: niente <wi-card>, card resa dal server', fn () =>
    !str_contains($authUsers, '<wi-card') && substr_count($authUsers, $card) === 4);

check('log/auth-users: griglia col-12, col-9, col-3 come prima', fn () =>
    str_starts_with($authUsers, '<div class="row g-3"><div class="col-12">'.$card)
    && str_contains($authUsers, '<div class="col-9"><div class="row g-3"><div class="col-12">'.$card)
    && str_contains($authUsers, '<div class="col-3"><div class="row g-3"><div class="col-12">'.$card));

check('log/auth-users: i metadati JSON in <pre> dentro una card annidata', fn () =>
    str_contains($authUsers, '<div class="col-12"><div class="col-12">'.$card.'<pre class="m-0">')
    && str_contains($authUsers, '&quot;') === false
    && str_contains($authUsers, '"a": 1'));

check('log/auth-users: i valori restano escapati', fn () =>
    str_contains($authUsers, 'login &lt;ok&gt;') && str_contains($authUsers, 'Mario &lt;Rossi&gt;'));

$consent = $page('log/consent/show.php', ['ITEM' => [
    'user_id' => 3, 'legal_document_id' => 7, 'action' => 'accept', 'source' => 'banner',
    'evidence_json' => '{"checkbox":true}', 'locale' => 'it', 'ip_address' => '::1',
    'user_agent' => 'curl', 'creation' => '2026-10-01',
]]);

check('log/consent: niente <wi-card>, sei card', fn () =>
    !str_contains($consent, '<wi-card') && substr_count($consent, $card) === 6);

check('log/consent: le due card affiancate sono col-6', fn () =>
    substr_count($consent, '<div class="col-6">'.$card) === 2
    && str_contains($consent, '<div class="col-9"><div class="row g-3"><div class="col-6">'));

check('log/consent: prova JSON in <pre>, badge azione non escapato', fn () =>
    str_contains($consent, '<pre class="m-0">') && str_contains($consent, '<span class="badge">accept</span>'));

$legal = $page('config/legal-documents/show.php', ['ITEM' => ['id' => 7], 'RESOURCE_CLASS' => FakeLegalResource::class]);

check('config/legal-documents: checkbox e testo in Card annidate, senza <wi-card>', fn () =>
    !str_contains($legal, '<wi-card')
    && str_contains($legal, '<div class="col-12">'.$card.'Accetto la <b>privacy</b></div></div></div>')
    && str_contains($legal, '<div class="col-12">'.$card.'<p>Testo</p></div></div></div>'));

check('wiCard() e wiAccordion() non esistono più, wiPreIfArray() sì', fn () =>
    !function_exists('wiCard') && !function_exists('wiAccordion') && function_exists('wiPreIfArray'));

$email = $page('log/email/show.php', ['ITEM' => [
    'user_id' => 3, 'subject' => 'Ciao <b>', 'from_email' => 'a@b.it', 'to_email' => 'c@d.it',
    'body_text' => "riga 1\nriga 2", 'attachments' => '["a.pdf"]', 'error_message' => '', 'status' => 'sent',
]]);

check('log/email: cinque card, le due centrali affiancate col-6', fn () =>
    !str_contains($email, '<wi-card') && substr_count($email, $card) === 5
    && substr_count($email, '<div class="col-6">'.$card) === 2
    && str_contains($email, '<div class="col-9"><div class="row g-3"><div class="col-12">'.$card)
    && str_contains($email, '<div class="col-3"><div class="row g-3"><div class="col-12">'.$card)
    && str_contains($email, 'Ciao &lt;b&gt;'));

$sqlError = $page('config/sql-error/show.php', ['ITEM' => [
    'table' => 'user', 'error_n' => 1064, 'error' => 'syntax', 'query' => 'SELECT 1', 'function' => 'sqlSelect',
]]);

check('config/sql-error: quattro card su col-8 e col-4', fn () =>
    !str_contains($sqlError, '<wi-card') && substr_count($sqlError, $card) === 4
    && str_contains($sqlError, '<div class="col-8"><div class="row g-3"><div class="col-12">'.$card)
    && str_contains($sqlError, '<div class="col-4"><div class="row g-3"><div class="col-12">'.$card));

$list = $page('resource/list.php', ['READONLY' => true, 'READONLY_NOTICE' => 'Solo <lettura>', 'TABLE_HTML' => '<table></table>']);

check('resource/list: avviso di sola lettura in una Card col-12', fn () =>
    !str_contains($list, '<wi-card')
    && str_contains($list, '<div class="col-12">'.$card.'    <div class="alert alert-warning mb-0">Solo &lt;lettura&gt;</div>'."\n".'</div></div></div>'));

$form = $page('resource/form.php', ['READONLY' => true, 'READONLY_NOTICE' => 'Bloccato', 'FIELDS' => [], 'SIDEBAR_FIELDS' => []]);

check('resource/form: avviso e campi in Card, senza <wi-card>', fn () =>
    !str_contains($form, '<wi-card') && substr_count($form, $card) === 2
    && str_contains($form, '<div class="alert alert-warning mb-0">Bloccato</div>'));

$formEdit = $page('resource/form.php', ['FIELDS' => [], 'SIDEBAR_FIELDS' => []]);

check('resource/form: in modifica campi e pulsante Salva in due Card', fn () =>
    !str_contains($formEdit, '<wi-card') && substr_count($formEdit, $card) === 2
    && str_contains($formEdit, 'Salva</button>'));

$layoutShow = (static function (): string {
    $TITLE = 'Titolo <x>';
    $PAGE_CONTENT = '<p>contenuto</p>';
    $BACK_URL = '';
    ob_start();
    include dirname(__DIR__, 3).'/app/view/layout/backend/show.php';

    return (string) ob_get_clean();
})();

check('layout backend.show: intestazione in Card, contenuto dopo', fn () =>
    !str_contains($layoutShow, '<wi-card')
    && str_contains($layoutShow, '<div class="row g-3">'."\n    ".'<div class="col-12">'.$card)
    && str_contains($layoutShow, 'Titolo &lt;x&gt;') && str_contains($layoutShow, '<p>contenuto</p>'));

$presenter = (new ReflectionClass(\Wonder\Backend\Support\ResourcePagePresenter::class))->newInstanceWithoutConstructor();
$fallback = $presenter->listFallback(new RuntimeException('db <giù>'));

check('ResourcePagePresenter::listFallback: avviso in Card, messaggio escapato', fn () =>
    str_starts_with($fallback, '<div class="col-12">'.$card.'<div class="alert alert-warning mb-0">')
    && str_contains($fallback, 'db &lt;giù&gt;'));

if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
}
defined('ROOT') || define('ROOT', $root);
$scheduler = $page('scheduler/schedules.php', ['READONLY' => true, 'READONLY_NOTICE' => 'Solo <lettura>', 'TABLE_HTML' => '<table></table>']);

// Prima Card->render('bootstrap') con un figlio stringa andava in Fatal.
check('scheduler in sola lettura: avviso in Card, poi la tabella', fn () =>
    str_starts_with($scheduler, '<div class="col-12">'.$card.'<div class="alert alert-warning mb-0">Solo &lt;lettura&gt;</div>')
    && str_contains($scheduler, '<table></table>'));

exit(summary());
