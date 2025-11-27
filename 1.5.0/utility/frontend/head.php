<?php 

    // Statistiche
        
        if (is_array($DB->database) && array_key_exists('stats', $DB->database) && $ACTIVE_STATISTICS) {

            $VALUES = [
                "visitor_id" => $VISITOR_ID,
                "session_id" => $SESSION_ID,
                "registered_user" => $REGISTERED_USER,
                "user_id" => $USER_ID,
                "page_title" => $SEO->title,
                "path" => isset($_SERVER['PATH']) ? $_SERVER['PATH'] : '',
                "oracle_home" => isset($_SERVER['ORACLE_HOME']) ? $_SERVER['ORACLE_HOME'] : '',
                "http_accept" => isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '',
                "http_accept_encoding" => isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '',
                "http_accept_language" => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '',
                "http_cookie" => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '',
                "http_host" => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                "http_user_agent" => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                "http_priority" => isset($_SERVER['HTTP_PRIORITY']) ? $_SERVER['HTTP_PRIORITY'] : '',
                "http_dnt" => isset($_SERVER['HTTP_DNT']) ? $_SERVER['HTTP_DNT'] : '',
                "http_upgrade_insicure_requests" => isset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']) ? $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] : '',
                "http_sec_fetch_dest" => isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '',
                "http_sec_fetch_mode" => isset($_SERVER['HTTP_SEC_FETCH_MODE']) ? $_SERVER['HTTP_SEC_FETCH_MODE'] : '',
                "http_sec_fetch_site" => isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '',
                "http_sec_fetch_user" => isset($_SERVER['HTTP_SEC_FETCH_USER']) ? $_SERVER['HTTP_SEC_FETCH_USER'] : '',
                "document_root" => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '',
                "remote_addr" => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                "remote_port" => isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '',
                "server_addr" => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '',
                "server_name" => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '',
                "server_admin" => isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : '',
                "server_port" => isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '',
                "request_scheme" => isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : '',
                "request_uri" => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                "https" => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '',
                "x_spdy" => isset($_SERVER['X_SPDY']) ? $_SERVER['X_SPDY'] : '',
                "ssl_protocol" => isset($_SERVER['SSL_PROTOCOL']) ? $_SERVER['SSL_PROTOCOL'] : '',
                "ssl_cipher" => isset($_SERVER['SSL_CIPHER']) ? $_SERVER['SSL_CIPHER'] : '',
                "ssl_cipher_usekeysize" => isset($_SERVER['SSL_CIPHER_USEKEYSIZE']) ? $_SERVER['SSL_CIPHER_USEKEYSIZE'] : '',
                "ssl_cipher_algkeysize" => isset($_SERVER['SSL_CIPHER_ALGKEYSIZE']) ? $_SERVER['SSL_CIPHER_ALGKEYSIZE'] : '',
                "script_filename" => isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '',
                "query_string" => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
                "script_uri" => isset($_SERVER['SCRIPT_URI']) ? $_SERVER['SCRIPT_URI'] : '',
                "script_url" => isset($_SERVER['SCRIPT_URL']) ? $_SERVER['SCRIPT_URL'] : '',
                "script_name" => isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '',
                "server_protocol" => isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : '',
                "server_software" => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
                "request_method" => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
                "x_lschache" => isset($_SERVER['X-LSCACHE']) ? $_SERVER['X-LSCACHE'] : '',
                "php_self" => isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '',
                "request_time_float" => isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : '',
                "request_time" => isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : ''
            ];
            
            $mysqli = $MYSQLI_CONNECTION['stats'];
            sqlInsert("visitors_log", $VALUES);
            $mysqli = $MYSQLI_CONNECTION['main'];
        
        }

    //

    # Sanifico la SEO
    $SEO->title = empty($SEO->title) ? "" : strip_tags($SEO->title);
    $SEO->description = empty($SEO->description) ? "" : substr(str_replace('"', "", strip_tags($SEO->description)), 0, 140); # Raccomandato tra i 50 e 160 caratteri

    $SQL_ANALYTICS = sqlSelect('analytics', ['id' => '1'], 1)->row;

    $ANALYTICS->tag_manager = (object) array();
    $ANALYTICS->pixel = (object) array();

    $ANALYTICS->tag_manager->id = $SQL_ANALYTICS['tag_manager'];
    $ANALYTICS->tag_manager->active = (!empty($ANALYTICS->tag_manager->id) && $ACTIVE_STATISTICS && ($SQL_ANALYTICS['active_tag_manager'] == "" || $SQL_ANALYTICS['active_tag_manager'] == "true")) ? true : false;
    $ANALYTICS->pixel->id = $SQL_ANALYTICS['pixel_facebook'];
    $ANALYTICS->pixel->active = (!empty($ANALYTICS->pixel->id) && $ACTIVE_STATISTICS && ($SQL_ANALYTICS['active_pixel_facebook'] == "" || $SQL_ANALYTICS['active_pixel_facebook'] == "true")) ? true : false;

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
        var NO_INTERNET_ALERT = null;

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
    <?php foreach (sqlSelect('css_font', ['visible' => 'true'])->row as $key => $row) { echo "<link href='{$row['link']}' rel='stylesheet'>"; } ?>

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