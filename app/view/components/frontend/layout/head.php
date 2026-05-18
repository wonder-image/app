<?php 

    $SEO ??= (object) [];
    $SOCIETY ??= (object) [];
    $ANALYTICS ??= (object) [];
    $DB ??= (object) [ 'database' => [] ];

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
    $societyName = (string) ($SOCIETY->name ?? '');
    $faviconPath = (string) ($PATH->favicon ?? '');
    $uploadLogoPath = (string) ($PATH->upload->logos ?? '');

    $SQL_ANALYTICS = [];

    if (sqlTableExists('analytics')) {
        $SQL_ANALYTICS = sqlSelect('analytics', ['id' => '1'], 1)->row;
    }

    $ANALYTICS->tag_manager = (object) array();
    $ANALYTICS->pixel = (object) array();

    $ANALYTICS->tag_manager->id = $SQL_ANALYTICS['tag_manager'] ?? '';
    $ANALYTICS->tag_manager->active = (
        !empty($ANALYTICS->tag_manager->id)
        && $ACTIVE_STATISTICS
        && (($SQL_ANALYTICS['active_tag_manager'] ?? '') === "" || ($SQL_ANALYTICS['active_tag_manager'] ?? '') === "true")
    ) ? true : false;
    $ANALYTICS->pixel->id = $SQL_ANALYTICS['pixel_facebook'] ?? '';
    $ANALYTICS->pixel->active = (
        !empty($ANALYTICS->pixel->id)
        && $ACTIVE_STATISTICS
        && (($SQL_ANALYTICS['active_pixel_facebook'] ?? '') === "" || ($SQL_ANALYTICS['active_pixel_facebook'] ?? '') === "true")
    ) ? true : false;

    if ($ANALYTICS->tag_manager->active) :

?>
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
<meta http-equiv="content-language" content="IT">
<meta http-equiv="Content-Type" content="text/html; iso-8859-7">
<meta name="robots" content="INDEX,FOLLOW">
<meta name="creation_Date" content="<?=e($seoDate)?>">
<meta name="revisit-after" content="1 days">

<meta property="og:title" content="<?=e($seoTitle)?>">
<meta property="og:description" content="<?=e($seoDescription)?>">
<meta property="og:image" content="<?=e($seoImage)?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?=e($seoUrl)?>">
<meta property="og:site_name" content="<?=e($societyName)?>">

<meta property="twitter:title" content="<?=e($seoTitle)?>" />
<meta property="twitter:description" content="<?=e($seoDescription)?>" />
<meta property="twitter:image" content="<?=e($seoImage)?>" />
<meta property="twitter:card" content="summary" />
<meta property="twitter:site" content="<?=e($seoUrl)?>" />
<meta name="twitter:creator" content="@<?=e($seoCreator)?>" />

<meta name="apple-mobile-web-app-title" content="<?=e($seoTitle)?>">

<?php

    if (!empty($SEO->breadcrumb)) {

        echo "<!-- Inizio BreadcrumbList => schema.org -->";
        echo breadcrumb($SEO->breadcrumb);
        echo "<!-- Fine BreadcrumbList => schema.org -->";

    }

?>

<?php

    if (!empty($SOCIETY->favicon)) {

        echo "<link rel='icon' href='".e($faviconPath)."'>";

    }

    if (!empty($SOCIETY->appIcon)) {

        echo "<link rel='apple-touch-icon' href='".e($SOCIETY->appIcon)."'>";

        $pathInfo = pathinfo($SOCIETY->appIcon);

        $name = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        foreach ($DEFAULT->appIcon as $size) {

            echo "<link rel='icon' sizes='".e($size)."x".e($size)."' href='".e($uploadLogoPath."/{$name}-{$size}.{$extension}")."'>";
            echo "<link rel='apple-touch-icon' sizes='".e($size)."x".e($size)."' href='".e($uploadLogoPath."/{$name}-{$size}.{$extension}")."'>";

        }

    }

?>

<script>

    const pathSite = <?=json_encode((string) ($PATH->site ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const pathApp = <?=json_encode((string) ($PATH->app ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const NO_INTERNET_ALERT = <?=json_encode((string) alertTheme(801), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

    const API_TOKEN = <?=json_encode((string) Wonder\App\Credentials::appToken(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_API_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->gcp_client_api_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_SITE_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->g_recaptcha_site_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_PLACE_ID = <?=json_encode((string) Wonder\App\Credentials::api()->g_maps_place_id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

</script>

<link rel="stylesheet" href="<?=$PATH->css?>/set-up/root.css">
<link rel="stylesheet" href="<?=$PATH->css?>/set-up/color.css">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php

    if (sqlTableExists('css_font')) {
        foreach (sqlSelect('css_font', ['visible' => 'true'])->row as $key => $row) {
            echo "<link href='".e($row['link'] ?? '')."' rel='stylesheet'>";
        }
    }

?>

<?=Wonder\App\Dependencies::Head()?>

<script>

    TranslationProvider.init(
        <?=json_encode(Wonder\Localization\TranslationProvider::$translations)?>,
        <?=json_encode(Wonder\Localization\TranslationProvider::$defaultTranslations)?>
    );

</script>

<?php if ($ANALYTICS->pixel->active) : ?>
<!-- Inizio Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?=json_encode((string) $ANALYTICS->pixel->id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?=e($ANALYTICS->pixel->id)?>&ev=PageView&noscript=1"
/></noscript>
<!-- Fine Meta Pixel -->
<?php endif; ?>
