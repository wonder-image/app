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

    # Sanifico la SEO
    $SEO->title = empty($SEO->title) ? "" : strip_tags($SEO->title);
    $SEO->description = empty($SEO->description) ? "" : substr(str_replace('"', "", strip_tags($SEO->description)), 0, 140); # Raccomandato tra i 50 e 160 caratteri

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
    })(window,document,'script','dataLayer','<?=$ANALYTICS->tag_manager->id?>');
</script>
<!-- Fine Google Tag Manager -->
<?php endif; ?>

<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Link canonico pagina -->
<link rel="canonical" href="<?=$SEO->url?>">

<!-- Impostazioni Multilingua -->
<?=Wonder\Localization\LanguageContext::renderHead($SEO->url);?>

<!-- Inizio SEO -->

    <title><?=$SEO->title?></title>

    <!-- Google -->
    <meta name="title" content="<?=$SEO->title?>">
    <meta name="description" content="<?=$SEO->description?>">
    <meta name="author" content="<?=$SEO->author?>">
    <meta name="copyright" content="<?=$SEO->copyright?>">
    <meta http-equiv="Reply-to" content="<?=$SEO->reply?>">
    <meta http-equiv="content-language" content="IT">
    <meta http-equiv="Content-Type" content="text/html; iso-8859-7">
    <meta name="robots" content="INDEX,FOLLOW">
    <meta name="creation_Date" content="<?=$SEO->date?>">
    <meta name="revisit-after" content="1 days">

    <!-- Open Graph Protocol -->
    <meta property="og:title" content="<?=$SEO->title?>">
    <meta property="og:description" content="<?=$SEO->description?>">
    <meta property="og:image" content="<?=$SEO->image?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?=$SEO->url?>">
    <meta property="og:site_name" content="<?=$SOCIETY->name?>">

    <!-- Twitter -->
    <meta property="twitter:title" content="<?=$SEO->title?>" />
    <meta property="twitter:description" content="<?=$SEO->description?>" />
    <meta property="twitter:image" content="<?=$SEO->image?>" />
    <meta property="twitter:card" content="summary" />
    <meta property="twitter:site" content="<?=$SEO->url?>" />
    <meta name="twitter:creator" content="@<?=$SEO->creator?>" />

    <!-- Apple -->
    <meta name="apple-mobile-web-app-title" content="<?=$SEO->title?>">

<!-- Fine SEO -->

<?php

    if (!empty($SEO->breadcrumb)) {

        echo "<!-- Inizio BreadcrumbList => schema.org -->";
        echo breadcrumb($SEO->breadcrumb);
        echo "<!-- Fine BreadcrumbList => schema.org -->";

    }

?>

<!-- Inizio icone -->
    <?php

        if (!empty($SOCIETY->favicon)) {

            echo "<link rel='icon' href='$PATH->favicon'>";

        }

        if (!empty($SOCIETY->appIcon)) {

            echo "<link rel='apple-touch-icon' href='$SOCIETY->appIcon'>";

            $pathInfo = pathinfo($SOCIETY->appIcon);

            $name = $pathInfo['filename'];
            $extension = $pathInfo['extension'];

            foreach ($DEFAULT->appIcon as $size) {
                
                echo "<link rel='icon' sizes='{$size}x{$size}' href='$PATH->upload/logos/{$name}-{$size}.{$extension}'>";
                echo "<link rel='apple-touch-icon' sizes='{$size}x{$size}' href='$PATH->upload/logos/{$name}-{$size}.{$extension}'>";
            
            }

        }

    ?>
<!-- Fine icone -->

<!-- Inizio Config -->

    <script>

        const pathSite = '<?=$PATH->site?>';
        const pathApp = '<?=$PATH->app?>';
        const NO_INTERNET_ALERT = `<?=alertTheme(801)?>`;

        const API_TOKEN = '<?=Wonder\App\Credentials::appToken()?>';
        const GOOGLE_API_KEY = '<?=Wonder\App\Credentials::api()->gcp_client_api_key?>';
        const GOOGLE_SITE_KEY = '<?=Wonder\App\Credentials::api()->g_recaptcha_site_key?>';
        const GOOGLE_PLACE_ID = '<?=Wonder\App\Credentials::api()->g_maps_place_id?>';

    </script>

    <link rel="stylesheet" href="<?=$PATH->css?>/set-up/root.css">
    <link rel="stylesheet" href="<?=$PATH->css?>/set-up/color.css">

<!-- Fine Config -->

<!-- Inizio librerie -->

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php

        if (sqlTableExists('css_font')) {
            foreach (sqlSelect('css_font', ['visible' => 'true'])->row as $key => $row) {
                echo "<link href='{$row['link']}' rel='stylesheet'>";
            }
        }

    ?>

    <?=Wonder\App\Dependencies::Head()?>

<!-- Fine librerie -->
    
<!-- Inizio traduzioni -->

    <script>
        
        TranslationProvider.init(
            <?=json_encode(Wonder\Localization\TranslationProvider::$translations)?>,
            <?=json_encode(Wonder\Localization\TranslationProvider::$defaultTranslations)?>
        );

    </script>

<!-- Fine traduzioni -->
 
<?php include $ROOT."/custom/utility/frontend/head.php"; ?>

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
    fbq('init', '<?=$ANALYTICS->pixel->id?>');
    fbq('track', 'PageView');
</script>
<noscript>
    <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?=$ANALYTICS->pixel->id?>&ev=PageView&noscript=1" />
</noscript>
<!-- Fine Meta Pixel -->
<?php endif; ?>
