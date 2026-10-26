<?php

    $SEO ??= (object) [];
    $SOCIETY ??= (object) [];
    $ANALYTICS ??= (object) [];
    $DB ??= (object) [ 'database' => [] ];

    // Il componente riceve solo le chiavi di LegacyGlobals::scope(): se l'head viene
    // renderizzato senza il bootstrap frontend il flag non arriva e le statistiche
    // resterebbero spente in silenzio. Stesso default di app/bootstrap/frontend.php.
    $ACTIVE_STATISTICS ??= \Wonder\App\LegacyGlobals::get('ACTIVE_STATISTICS', true);

    foreach ([
        'title' => '',
        'description' => '',
        'author' => '',
        'copyright' => '',
        'reply' => '',
        'date' => date('d/m/Y'),
        'image' => '',
        'url' => '',
        'creator' => '',
        'breadcrumb' => '',
    ] as $field => $value) {
        if (!isset($SEO->$field)) {
            $SEO->$field = $value;
        }
    }

    foreach ([
        'name' => '',
        'favicon' => '',
        'appIcon' => '',
    ] as $field => $value) {
        if (!isset($SOCIETY->$field)) {
            $SOCIETY->$field = $value;
        }
    }

    $SEO->title = empty($SEO->title) ? "" : strip_tags($SEO->title);
    $SEO->description = empty($SEO->description) ? "" : substr(str_replace('"', "", strip_tags($SEO->description)), 0, 140);
    $seoUrl = (string) ($SEO->url ?? '');
    $seoTitle = (string) ($SEO->title ?? '');
    $seoDescription = (string) ($SEO->description ?? '');
    $seoAuthor = (string) ($SEO->author ?? '');
    $seoCopyright = (string) ($SEO->copyright ?? '');
    $seoReply = (string) ($SEO->reply ?? '');
    $seoDate = (string) ($SEO->date ?? '');
    $seoImage = (string) ($SEO->image ?? '');
    $seoCreator = (string) ($SEO->creator ?? '');
    $seoRobots = strtoupper(trim((string) ($SEO->robots ?? 'INDEX,FOLLOW')));
    if (!in_array($seoRobots, ['INDEX,FOLLOW', 'INDEX,NOFOLLOW', 'NOINDEX,FOLLOW', 'NOINDEX,NOFOLLOW'], true)) {
        $seoRobots = 'INDEX,FOLLOW';
    }
    $societyName = (string) ($SOCIETY->name ?? '');
    $seoLang = \Wonder\Localization\LanguageContext::getLang();
    $seoOgType = trim((string) ($SEO->ogType ?? '')) ?: 'website';
    $seoOgMeta = is_array($SEO->ogMeta ?? null) ? $SEO->ogMeta : [];
    $faviconPath = (string) ($PATH->favicon ?? '');

    // Una lettura per richiesta (RequestMemo, scope = tabella: le scritture lo svuotano).
    $SQL_ANALYTICS = \Wonder\App\Support\RequestMemo::remember(
        'analytics',
        'head:'.sqlMemoConnectionKey($GLOBALS['mysqli'] ?? null),
        static fn () => sqlTableExists('analytics') ? sqlSelect('analytics', ['id' => '1'], 1)->row : []
    );

    $ANALYTICS->tag_manager = (object) array();
    $ANALYTICS->pixel = (object) array();

    $ANALYTICS->tag_manager->id = $SQL_ANALYTICS['tag_manager'] ?? '';
    $ANALYTICS->tag_manager->active = (
        !empty($ANALYTICS->tag_manager->id)
        && $ACTIVE_STATISTICS
        && (($SQL_ANALYTICS['active_tag_manager'] ?? '') === "" || ($SQL_ANALYTICS['active_tag_manager'] ?? '') === "true")
    ) ? true : false;
    // Pixel: resta in $ANALYTICS per gli override dei siti, il core non lo emette.
    $ANALYTICS->pixel->id = $SQL_ANALYTICS['pixel_facebook'] ?? '';
    $ANALYTICS->pixel->active = (
        !empty($ANALYTICS->pixel->id)
        && $ACTIVE_STATISTICS
        && (($SQL_ANALYTICS['active_pixel_facebook'] ?? '') === "" || ($SQL_ANALYTICS['active_pixel_facebook'] ?? '') === "true")
    ) ? true : false;

?>
<?php $dataLayerFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR; ?>
<script>
<?=\Wonder\Frontend\Support\DataLayer::bootstrapScript($_SESSION['user_id'] ?? null)?>
<?php foreach (\Wonder\Auth\Frontend\AuthSession::consumeEvents() as $authEvent): ?>
window.dataLayer.push(<?=json_encode($authEvent, $dataLayerFlags)?>);
<?php endforeach; ?>
</script>
<?php if ($ANALYTICS->tag_manager->active) : ?>
<!-- Inizio Google Tag Manager -->
<script>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer',<?=json_encode((string) $ANALYTICS->tag_manager->id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>);
</script>
<!-- Fine Google Tag Manager -->
<?php endif; ?>

<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="canonical" href="<?=e($seoUrl)?>">

<?=Wonder\Localization\LanguageContext::renderHead($seoUrl);?>

<title><?=e($seoTitle)?></title>

<meta name="title" content="<?=e($seoTitle)?>">
<meta name="description" content="<?=e($seoDescription)?>">
<meta name="author" content="<?=e($seoAuthor)?>">
<meta name="copyright" content="<?=e($seoCopyright)?>">
<meta http-equiv="Reply-to" content="<?=e($seoReply)?>">
<meta name="robots" content="<?=e($seoRobots)?>">
<meta name="creation_Date" content="<?=e($seoDate)?>">
<meta name="revisit-after" content="1 days">

<meta property="og:title" content="<?=e($seoTitle)?>">
<meta property="og:description" content="<?=e($seoDescription)?>">
<meta property="og:image" content="<?=e($seoImage)?>">
<meta property="og:type" content="<?=e($seoOgType)?>">
<meta property="og:url" content="<?=e($seoUrl)?>">
<meta property="og:site_name" content="<?=e($societyName)?>">
<?php foreach ($seoOgMeta as $ogProperty => $ogContent) : ?>
<?php if (is_string($ogProperty) && $ogProperty !== '' && is_scalar($ogContent) && (string) $ogContent !== '') : ?>
<meta property="<?=e($ogProperty)?>" content="<?=e((string) $ogContent)?>">
<?php endif; ?>
<?php endforeach; ?>

<meta property="twitter:title" content="<?=e($seoTitle)?>" />
<meta property="twitter:description" content="<?=e($seoDescription)?>" />
<meta property="twitter:image" content="<?=e($seoImage)?>" />
<meta property="twitter:card" content="summary" />
<meta property="twitter:site" content="<?=e($seoUrl)?>" />
<meta name="twitter:creator" content="@<?=e($seoCreator)?>" />

<meta name="apple-mobile-web-app-title" content="<?=e($seoTitle)?>">

<?php

    $schemaOrg = is_array($SEO->schemaOrg ?? null) ? $SEO->schemaOrg : [];
    $schemaGraph = [];
    if ($schemaOrg !== []) {
        $schemaGraph = isset($schemaOrg['@graph']) ? (array) $schemaOrg['@graph']
            : (array_is_list($schemaOrg) ? $schemaOrg : [$schemaOrg]);
        foreach ($schemaGraph as &$entity) {
            if (is_array($entity)) unset($entity['@context']);
        }
        unset($entity);
    }
    // Organizzazione e sito su ogni pagina indicizzabile: le entità della
    // pagina le riferiscono via @id (es. il venditore di un'offerta).
    // Le pagine private (NOINDEX: login, account) non hanno JSON-LD pubblico.
    if (!str_contains($seoRobots, 'NOINDEX')) {
        $siteUrl = (string) ($PATH->site ?? '');
        $schemaGraph = \Wonder\Frontend\Support\SiteSchema::merge($schemaGraph, [
            \Wonder\Frontend\Support\SiteSchema::organization($SOCIETY, $siteUrl, (string) ($PATH->logo ?? '')),
            \Wonder\Frontend\Support\SiteSchema::webSite($siteUrl, $societyName, $seoLang, (string) ($SEO->searchUrl ?? '')),
        ]);
    }
    if (!empty($SEO->breadcrumb)) {
        $schemaGraph = array_values(array_filter($schemaGraph, static fn ($entity): bool => is_array($entity) && ($entity['@type'] ?? '') !== 'BreadcrumbList'));
        $schemaGraph[] = json_decode(breadcrumb($SEO->breadcrumb, false), true);
    }
    if ($schemaGraph !== []) {
        echo '<script type="application/ld+json">'.json_encode([
            '@context' => 'https://schema.org', '@graph' => $schemaGraph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE).'</script>';
    }

?>

<?php

    if (!empty($SOCIETY->favicon)) {

        echo "<link rel='icon' href='".e($faviconPath)."'>";

    }

    if (!empty($SOCIETY->appIcon)) {

        echo "<link rel='apple-touch-icon' href='".e($SOCIETY->appIcon)."'>";

        # Le misure stanno accanto all'originale: la cartella va letta
        # dall'URL dell'icona, non ricostruita a mano. La resource che
        # ospita i loghi può cambiare cartella, e ricomporla qui vuol dire
        # sbagliarla in silenzio.
        $pathInfo = pathinfo((string) $SOCIETY->appIcon);

        $appIconDirectory = rtrim((string) ($pathInfo['dirname'] ?? ''), '/');
        $name = (string) ($pathInfo['filename'] ?? '');
        $extension = (string) ($pathInfo['extension'] ?? '');

        if ($name !== '' && $extension !== '') {

            foreach (\Wonder\App\RuntimeDefaults::appIconSizes() as $size) {

                $appIconSized = $appIconDirectory."/{$name}-{$size}.{$extension}";

                echo "<link rel='icon' sizes='".e($size)."x".e($size)."' href='".e($appIconSized)."'>";
                echo "<link rel='apple-touch-icon' sizes='".e($size)."x".e($size)."' href='".e($appIconSized)."'>";

            }

        }

    }

?>

<script>

    const pathSite = <?=json_encode((string) ($PATH->site ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const pathApp = <?=json_encode((string) ($PATH->app ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const pathApi = <?=json_encode((string) ($PATH->api ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const NO_INTERNET_ALERT = <?=json_encode((string) alertTheme(801), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

    const API_TOKEN = <?=json_encode(Wonder\Api\Token\ApiTokens::pageToken(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_API_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->gcp_client_api_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_SITE_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->g_recaptcha_site_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_PLACE_ID = <?=json_encode((string) Wonder\App\Credentials::api()->g_maps_place_id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

</script>

<?php
    // Design token (variabili CSS) inline: piccoli e alla base di tutta la cascata,
    // toglierli dal render-blocking anticipa il primo paint. Fallback a <link> se
    // il file non è risolvibile (vedi __inline_css).
?>
<?=__inline_css($PATH->css.'/set-up/root.css')?>
<?=__inline_css($PATH->css.'/set-up/color.css')?>

<?php

    // Una lettura per richiesta; null = tabella assente.
    $CSS_FONT_ROWS = \Wonder\App\Support\RequestMemo::remember(
        'css_font',
        'head:'.sqlMemoConnectionKey($GLOBALS['mysqli'] ?? null),
        static fn () => sqlTableExists('css_font') ? sqlSelect('css_font', ['visible' => 'true', 'deleted' => 'false'])->row : null
    );

    if ($CSS_FONT_ROWS !== null) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        // Link relativi fatti assoluti, display=swap su Google, ogni foglio una volta.
        foreach (Wonder\App\Support\CssFontLinks::hrefs($CSS_FONT_ROWS, (string) ($PATH->site ?? '')) as $fontLink) {
            if (str_contains($fontLink, 'fonts.googleapis.com')) {
                $escapedFontLink = e($fontLink);
                echo "<link rel='preload' as='style' href='".$escapedFontLink."' onload=\"this.onload=null;this.rel='stylesheet'\">";
                echo "<noscript><link href='".$escapedFontLink."' rel='stylesheet'></noscript>";
            } else {
                // Il foglio locale è piccolo: inline, con gli url() dei woff2 resi assoluti.
                echo Wonder\App\Support\Asset::inlineStyle($fontLink, null, null, 32768, true)
                    ?? "<link href='".e($fontLink)."' rel='stylesheet'>";
            }
        }
    }

?>

<?=Wonder\App\Dependencies::Head()?>
<?=\Wonder\View\View::renderHead()?>

<?php // Con script deferred, un module inline gira dopo head.js e prima di body-end.js (select.js usa __t subito) ?>
<script<?=Wonder\App\Dependencies::isFrontendDeferred() ? ' type="module"' : ''?>>

    TranslationProvider.init(
      <?=json_encode(Wonder\Localization\TranslationProvider::$translations)?>,
      <?=json_encode(Wonder\Localization\TranslationProvider::$defaultTranslations)?>
    );

</script>

<?php // Meta Pixel: non più emesso dal core, va configurato come tag GTM soggetto al consenso. ?>
