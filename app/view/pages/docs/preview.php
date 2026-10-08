<?php

/**
 * La pagina dentro l'iframe di un'anteprima: un documento minimo con i soli
 * asset del tema chiesto e l'HTML di un esempio. Variabili da
 * `Wonder\Docs\PreviewPage::build()` più `paths`, `token`, `lang`.
 *
 * @var \Wonder\Docs\ComponentDoc $doc
 * @var \Wonder\Docs\Example $example
 * @var \Wonder\Docs\RenderResult $result
 * @var string $theme
 * @var string $scheme
 * @var string $tokens
 * @var string $title
 */

$paths = is_array($paths ?? null) ? $paths : [];
$token = (string) ($token ?? '');
$lang = (string) ($lang ?? 'it');
$translations = class_exists(\Wonder\Localization\TranslationProvider::class)
    ? (array) (\Wonder\Localization\TranslationProvider::$translations ?? [])
    : [];
$defaultTranslations = class_exists(\Wonder\Localization\TranslationProvider::class)
    ? (array) (\Wonder\Localization\TranslationProvider::$defaultTranslations ?? [])
    : [];
$json = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';

?><!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-bs-theme="<?= e($scheme) ?>" data-wi-docs-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="data:,">
    <title><?= e($title) ?></title>

    <script>
        const pathSite = <?= $json((string) ($paths['site'] ?? '')) ?>;
        const pathApp = <?= $json((string) ($paths['app'] ?? '')) ?>;
        const pathApi = <?= $json((string) ($paths['api'] ?? '')) ?>;
        const NO_INTERNET_ALERT = '';
        const API_TOKEN = <?= $json($token) ?>;
        const GOOGLE_API_KEY = '';
        const GOOGLE_SITE_KEY = '';
        const GOOGLE_PLACE_ID = '';
    </script>

    <?= $tokens ?>

    <?= \Wonder\App\Dependencies::Head() ?>

    <script>
        if (window.TranslationProvider && typeof TranslationProvider.init === 'function') {
            TranslationProvider.init(<?= $json($translations) ?>, <?= $json($defaultTranslations) ?>);
        }
    </script>

    <style data-wi-docs-preview-style>
        html, body { height: auto !important; min-height: 0 !important; box-sizing: border-box !important; }
        html { overflow-y: auto; overflow-x: hidden; }
        body { margin: 0 !important; padding: 1rem !important; }
        [data-wi-docs-preview] { position: relative; min-height: 1px; }
        /* Le label Wonder sono assolute: senza un antenato posizionato finiscono tutte nell'angolo della pagina. */
        .wi-input-container { position: relative; }
        .wi-docs-preview-error { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8125rem; white-space: pre-wrap; word-break: break-word; border: 1px solid #dc3545; border-radius: .5rem; background: rgba(220, 53, 69, .08); color: #b02a37; padding: .75rem 1rem; margin: 0; }
        .wi-docs-preview-error strong { display: block; margin-bottom: .25rem; font-family: system-ui, sans-serif; }
    </style>
</head>
<body class="<?= $theme === 'bootstrap' ? 'bg-body text-body' : 'wi-docs-preview-body' ?>">

    <div data-wi-docs-preview data-wi-docs-component="<?= e($doc->getSlug()) ?>" data-wi-docs-example="<?= e((string) $index) ?>">
        <?php if ($result->error !== null) : ?>
            <pre class="wi-docs-preview-error"><strong>Errore nell'esempio «<?= e($example->getTitle()) ?>» (<?= e(\Wonder\Docs\ThemeSupport::label($theme)) ?>)</strong><?= e($result->error) ?></pre>
        <?php else : ?>
            <?= $result->html ?>
        <?php endif; ?>
    </div>

    <?= \Wonder\App\Dependencies::Body() ?>

    <script data-wi-docs-preview-script>
        (function () {
            // L'altezza è quella del contenuto (il riquadro dell'esempio, con
            // ciò che sporge da lui: menu, calendari), non del documento: il
            // documento riempie sempre l'iframe e misurarlo farebbe crescere
            // l'iframe senza fine.
            var send = function () {
                var box = document.querySelector('[data-wi-docs-preview]');
                var body = document.body;
                if (!box || !body) { return; }
                var styles = window.getComputedStyle(body);
                var padding = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
                var height = Math.ceil(Math.max(box.scrollHeight, box.getBoundingClientRect().height) + padding);
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'wi-preview:height', height: height }, '*');
                }
            };
            var schedule = function () { window.requestAnimationFrame(send); };
            window.addEventListener('load', function () {
                if (<?= $theme === 'bootstrap' ? 'true' : 'false' ?> && typeof window.setUpPage === 'function') {
                    try { window.setUpPage(); } catch (error) { if (window.console) { console.warn(error); } }
                }
                send();
                window.setTimeout(send, 300);
            });
            window.addEventListener('message', function (event) {
                var data = event.data;
                if (data && data.type === 'wi-preview:scheme' && (data.scheme === 'light' || data.scheme === 'dark')) {
                    document.documentElement.setAttribute('data-bs-theme', data.scheme);
                    schedule();
                }
            });
            if (window.ResizeObserver) {
                var box = document.querySelector('[data-wi-docs-preview]');
                if (box) { new ResizeObserver(schedule).observe(box); }
            }
            if (window.MutationObserver) {
                new MutationObserver(schedule).observe(document.documentElement, { attributes: true, childList: true, subtree: true });
            }
            schedule();
        })();
    </script>

</body>
</html>
