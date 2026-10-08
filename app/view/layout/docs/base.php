<?php

/**
 * Il layout del catalogo quando lo serve il server autonomo del pacchetto
 * (`php bin/docs.php`): solo gli asset Bootstrap del backend, una barra con il
 * titolo e il passaggio chiaro/scuro, nessuna sessione e nessun database.
 *
 * @var string $TITLE
 * @var string $PAGE_CONTENT
 * @var string $VERSION
 * @var \Wonder\Docs\Urls $urls
 */

?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($TITLE ?? 'Catalogo dei componenti') ?></title>

    <link rel="icon" href="data:,">

    <script>
        // Le variabili globali che il JS del backend della lib si aspetta.
        const pathSite = <?= json_encode(defined('APP_URL') ? APP_URL : '', JSON_UNESCAPED_SLASHES) ?>;
        const pathApp = <?= json_encode(defined('APP_URL') ? APP_URL.'/app' : '', JSON_UNESCAPED_SLASHES) ?>;
        const pathApi = <?= json_encode(defined('APP_URL') ? APP_URL.'/api' : '', JSON_UNESCAPED_SLASHES) ?>;
        const NO_INTERNET_ALERT = '';
        const API_TOKEN = '';
        const GOOGLE_API_KEY = '';
        const GOOGLE_SITE_KEY = '';
        const GOOGLE_PLACE_ID = '';
    </script>

    <script>
        if (localStorage.theme != 'dark' && localStorage.theme != 'light') {
            localStorage.setItem('theme', window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        }
        document.documentElement.setAttribute('data-bs-theme', localStorage.theme);
    </script>

    <?= \Wonder\App\Dependencies::Head() ?>

    <style>
        body { background: var(--bs-body-bg); }
        .wi-docs-navbar { position: sticky; top: 0; z-index: 1020; }
    </style>
</head>
<body>

    <nav class="navbar wi-docs-navbar bg-body border-bottom px-3">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e($urls->index()) ?>">
            <i class="bi bi-puzzle" aria-hidden="true"></i>
            <span>Catalogo dei componenti</span>
            <small class="text-body-secondary fw-normal">wonder-image/app <?= e($VERSION ?? '') ?></small>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="https://github.com/wonder-image/app/blob/main/docs/components/README.md" target="_blank" rel="noopener noreferrer"><i class="bi bi-book" aria-hidden="true"></i> Guida</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-wi-docs-page-scheme title="Chiaro / scuro" aria-label="Chiaro / scuro">
                <i class="bi bi-sun-fill" aria-hidden="true"></i><i class="bi bi-moon-stars-fill d-none" aria-hidden="true"></i>
            </button>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <?= $PAGE_CONTENT ?>
    </main>

    <?= \Wonder\App\Dependencies::Body() ?>

    <script>
        if (window.TranslationProvider && typeof TranslationProvider.init === 'function') {
            TranslationProvider.init(
                <?= json_encode((array) (\Wonder\Localization\TranslationProvider::$translations ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
                <?= json_encode((array) (\Wonder\Localization\TranslationProvider::$defaultTranslations ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
            );
        }
        (function () {
            var button = document.querySelector('[data-wi-docs-page-scheme]');
            if (!button) { return; }
            var paint = function () {
                var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                button.querySelector('.bi-sun-fill').classList.toggle('d-none', dark);
                button.querySelector('.bi-moon-stars-fill').classList.toggle('d-none', !dark);
            };
            button.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                try { localStorage.setItem('theme', next); } catch (error) {}
                paint();
            });
            paint();
        })();
    </script>

</body>
</html>
