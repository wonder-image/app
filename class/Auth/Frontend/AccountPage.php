<?php

namespace Wonder\Auth\Frontend;

use Wonder\View\View;

/** SEO e viste del pannello account del core. Le viste sono sigillate: niente sostituzioni da `custom/`. */
final class AccountPage
{
    /** `$data`: `active`, `title`, `seo_url`, `modals`, `errors` e i dati della pagina. */
    public static function render(string $view, array $data): void
    {
        self::seo((string) ($data['title'] ?? __t('account.title')), (string) ($data['seo_url'] ?? ''));

        $notice = (string) ($_SESSION['wonder_account_notice'] ?? '');
        unset($_SESSION['wonder_account_notice']);

        $panel = AccountRoutes::panel();
        $user = $data['user'] ?? \infoUser((int) ($_SESSION['user_id'] ?? 0), 'id');

        View::make($view, $data + [
            'account_panel' => $panel,
            'user' => $user,
            'navigation' => $panel->navigation($user, (string) ($data['active'] ?? '')),
            'logout_url' => AccountRoutes::auth()->route('logout'),
            'logout_token' => AuthSession::csrfToken(),
            'notice' => $notice,
            'errors' => [],
            'modals' => [],
            'head' => $panel->head(),
        ])->render();
    }

    /** SEO del pannello: mai indicizzato. */
    public static function seo(string $title, string $url): void
    {
        global $SEO;

        $SEO->title = $title;
        $SEO->description = (string) __t('account.seo.description');
        $SEO->url = $url;
        $SEO->breadcrumb = [];
        $SEO->robots = 'NOINDEX,NOFOLLOW';
    }

    public static function view(string $page): string
    {
        return dirname(__DIR__, 3).'/app/view/pages/frontend/account/'.basename($page).'.php';
    }
}
