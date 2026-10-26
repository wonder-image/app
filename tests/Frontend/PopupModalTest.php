<?php
/** php tests/Frontend/PopupModalTest.php */
declare(strict_types=1);

// Il modello vero legge dal database: qui restituisce il payload del test.
namespace Wonder\App\Models\Communications {
    final class Popup
    {
        public static ?array $payload = null;

        public static function modalPayloadForPageKey(string $pageKey): ?array
        {
            return self::$payload;
        }
    }
}

namespace {
    require __DIR__.'/../../vendor/autoload.php';
    require __DIR__.'/../harness.php';

    use Wonder\App\LegacyGlobals;
    use Wonder\App\Models\Communications\Popup;
    use Wonder\App\Theme;

    define('APP_URL', 'https://example.test');
    define('ROOT', dirname(__DIR__, 2));
    define('ASSETS_VERSION', 'test');
    define('APP_VERSION', 'test');

    function __t(string $key): string { return $key === 'components.buttons.more' ? 'Scopri' : $key; }
    function __ri(string $image) { return \Wonder\Elements\Media\Image::src($image); }

    Theme::set('wonder');
    LegacyGlobals::share(['ROUTE_META' => ['area' => 'frontend', 'name' => 'home']]);
    $_SESSION = [];

    $popup = static function (array $payload): string {
        Popup::$payload = $payload + [
            'code' => 'promo-1', 'title' => 'Saldi <30%>', 'url' => '', 'url_label' => '', 'view' => '',
            'image' => null, 'content_class' => 'bg-white tx-black', 'footer_class' => 'b-0 bg-white tx-black',
        ];
        unset($GLOBALS['WI_POPUP_RENDERED']);
        ob_start();
        include __DIR__.'/../../app/view/components/frontend/overlay/popup.php';

        return (string) ob_get_clean();
    };

    $html = $popup(['url' => '/saldi/', 'image' => 'promo.jpg']);

    check('popup: Modal frontend già aperta, senza script inline', fn () =>
        str_starts_with($html, '<section class="wi-modal wi-show" id="promo-1" role="dialog"')
        && !str_contains($html, 'aria-hidden="true" inert')
        && !str_contains($html, '<script') && !str_contains($html, 'modal('));

    check('popup: titolo escapato con la classe subtitle, header senza bordo', fn () =>
        str_contains($html, 'class="wi-modal-header b-0"')
        && str_contains($html, 'class="wi-modal-title subtitle">Saldi &lt;30%&gt;'));

    check('popup: i colori del popup sul dialogo e sul fondo', fn () =>
        str_contains($html, 'class="content wi-modal-content c-w bg-white tx-black"')
        && str_contains($html, 'class="wi-modal-footer d-flex j-content-end gap-3 b-0 bg-white tx-black"'));

    check('popup: immagine nel corpo, link con freccia che chiude la finestra', fn () =>
        str_contains($html, '<div class="wi-modal-body no-scrollbar"><img src="promo.jpg"')
        && str_contains($html, '<a href="/saldi/" class="btn btn-primary btn-arrow btn-icon-right wi-close-modal">Scopri <i class="bi bi-chevron-right"></i></a>'));

    $plain = $popup([]);

    check('popup: senza URL niente footer, senza immagine corpo vuoto', fn () =>
        !str_contains($plain, 'wi-modal-footer') && str_contains($plain, '<div class="wi-modal-body no-scrollbar"></div>'));

    $_SESSION = ['promo-1' => 2];

    check('popup: il contatore di sessione ferma il popup dopo "view" visite', fn () =>
        $popup(['view' => '2']) === '' && $_SESSION['promo-1'] === 2
        && $popup(['view' => '3']) !== '' && $_SESSION['promo-1'] === 3);

    check('popup: con un codice non valido come selettore non esce niente, e la pagina regge', fn () =>
        $popup(['code' => 'promo 2026']) === '' && $popup(['code' => '1promo']) === ''
        && !isset($_SESSION['promo 2026']));

    $_SESSION = [];
    $first = $popup([]);
    ob_start();
    include __DIR__.'/../../app/view/components/frontend/overlay/popup.php';
    $second = (string) ob_get_clean();

    check('popup: una sola volta per richiesta, anche se il layout lo include due volte', fn () =>
        $first !== '' && $second === '' && $_SESSION['promo-1'] === 1);

    check('overlay.modal e ui.button non esistono più', fn () =>
        !is_file(__DIR__.'/../../app/view/components/frontend/overlay/modal.php')
        && !is_file(__DIR__.'/../../app/view/components/frontend/ui/button.php'));

    exit(summary());
}
