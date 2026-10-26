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
file_put_contents($root.'/custom/view/layout/backend/show.php', '<?php echo $PAGE_CONTENT;');
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

exit(summary());
