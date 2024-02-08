<?php 

    // Statistiche
        
        if (is_array($DB->database) && array_key_exists('stats', $DB->database) && $ACTIVE_STATISTICS == true) {

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

    $SOCIETY_LOGOS = sqlSelect('logos', ['id' => '1'], 1)->row;
    $ANALYTICS = sqlSelect('analytics', ['id' => '1'], 1)->row;

    # Sanifico la SEO
    $SEO->title = str_replace('<br />', '', $SEO->title);
    $SEO->description = str_replace('<br />', '', $SEO->description);

    $TAG_MANAGER = $ANALYTICS['tag_manager'];
    $PIXEL_FACEBOOK = $ANALYTICS['pixel_facebook'];

    if ($TAG_MANAGER != '' && $ACTIVE_STATISTICS == true) :

?>
<!-- Inizio Google Tag Manager -->
<script>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?=$TAG_MANAGER?>');
</script>
<!-- Fine Google Tag Manager -->
<?php endif; ?>

<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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

<!-- Inizio icone -->
    <?php


        if ($SOCIETY_LOGOS['favicon'] != "") {

            echo "<link rel='icon' href='$PATH->favicon'>";

        }

        if ($SOCIETY_LOGOS['app_icon'] != "") {

            echo "<link rel='apple-touch-icon' href='$PATH->appIcon'>";

            foreach ($DEFAULT->appIcon as $size) {
                
                echo "<link rel='icon' sizes='{$size}x{$size}' href='$PATH->upload/logos/{$size}x{$size}-App-Icon.png'>";
                echo "<link rel='apple-touch-icon' sizes='{$size}x{$size}' href='$PATH->upload/logos/{$size}x{$size}-App-Icon.png'>";
            
            }

        }

    ?>
<!-- Fine icone -->

<!-- Inizio file fondamentali  -->

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    
    <!--  Moment.js  -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment.min.js" referrerpolicy="no-referrer"></script>

    <!-- JQuery - Datepicker -->
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css">

    <!-- JQuery - Timepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js" integrity="sha512-s5u/JBtkPg+Ff2WEr49/cJsod95UgLHbC00N/GglqdQuLnYhALncz8ZHiW/LxDRGduijLKzeYb7Aal9h3codZA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.css" integrity="sha512-LT9fy1J8pE4Cy6ijbg96UkExgOjCqcxAC7xsnv+mLJxSvftGVmmc236jlPTZXPcBRQcVOWoK1IJhb1dAjtb4lQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php foreach (sqlSelect('css_font', ['visible' => 'true'])->row as $key => $row) { echo "<link href='{$row['link']}' rel='stylesheet'>"; } ?>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Animazioni AOS -->
    <link id="aos-css" href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Swiper.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <link rel="stylesheet" href="<?=$PATH->lib?>/swiper/effect-shutters.min.css" />
    <link rel="stylesheet" href="<?=$PATH->lib?>/swiper/effect-slicer.min.css" />
    <link rel="stylesheet" href="<?=$PATH->lib?>/swiper/effect-carousel.min.css" />
    <link rel="stylesheet" href="<?=$PATH->lib?>/swiper/swiper-gl.min.css" />
    
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="<?=$PATH->lib?>/swiper/effect-shutters.min.js"></script>
    <script src="<?=$PATH->lib?>/swiper/effect-slicer.min.js"></script>
    <script src="<?=$PATH->lib?>/swiper/effect-carousel.min.js"></script>
    <script src="<?=$PATH->lib?>/swiper/swiper-gl.min.js"></script>

    <!-- Image Compare -->
    <link rel="stylesheet" href="https://unpkg.com/image-compare-viewer@1.6.2/dist/image-compare-viewer.min.css">
    <script src="https://unpkg.com/image-compare-viewer@1.6.2/dist/image-compare-viewer.min.js"></script>

    <!-- Fancybox -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">

    <!-- Panzoom -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/panzoom/panzoom.umd.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/panzoom/panzoom.css">

    <!-- Video.js -->
    <link href="https://unpkg.com/video.js@7/dist/video-js.min.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/city/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/fantasy/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/forest/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/sea/index.css" rel="stylesheet">

    <!-- Typed.js -->
    <script src="https://unpkg.com/typed.js@2.0.15/dist/typed.umd.js"></script>

    <!-- Autonumeric -->
    <script src="https://cdn.jsdelivr.net/npm/autonumeric@4.10.0/dist/autoNumeric.min.js"></script>

    <!-- CountUp -->
    <script src="<?=$PATH->lib?>/countup/countUp.umd.js"></script>

    <script>

        const pathSite = '<?=$PATH->site?>';
        const pathApp = '<?=$PATH->app?>';

        var NO_INTERNET_ALERT = null;
        
        $.ajax({
            type: "POST",
            url: pathApp+'/api/alert.php',
            data: { 
                post: 'true',
                frontend: 'true',
                alert: 801
            }, 
            success: function (data) {
                NO_INTERNET_ALERT = data;
            }
        });

    </script>

    <!-- Fundamental .js -->
    <script src="<?=$PATH->appJs?>/global/form/autonumeric.js"></script>
    <script src="<?=$PATH->appJs?>/global/utility.js"></script>
    <script src="<?=$PATH->appJs?>/global/fetch.js"></script>
    <script src="<?=$PATH->appJs?>/global/canvas.js"></script>

    <script src="<?=$PATH->appJs?>/frontend/utility.js"></script>
    <script src="<?=$PATH->appJs?>/frontend/scroll.js"></script>
    <script src="<?=$PATH->appJs?>/frontend/form/list.js"></script>
    <script src="<?=$PATH->appJs?>/frontend/form/input.js"></script>

    <!-- Custom .css -->
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/root/set-up.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/root/color.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/root/input.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/root/component.php">

    <!-- Fundamental .css -->
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/lib.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/main.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/resize.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/position.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/grid.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/margin.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/padding.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/section.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/dimension.php">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/function.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/class/text.css">

    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/header.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/hamburger/hamburger.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/hamburger/hamburger-1.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/mobile-nav.css">

    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/alert.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/modal.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/button.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/dropdown.css">

    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/input.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/date.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/checkbox.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/select.css">

<!-- Fine file fondamentali  -->

<?php include $ROOT."/custom/utility/frontend/head.php"; ?>

<?php if ($PIXEL_FACEBOOK != '' && $ACTIVE_STATISTICS == true) : ?>
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
    fbq('init', '<?=$PIXEL_FACEBOOK?>');
    fbq('track', 'PageView');
</script>
<noscript>
    <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?=$PIXEL_FACEBOOK?>&ev=PageView&noscript=1" />
</noscript>
<!-- Fine Meta Pixel -->
<?php endif; ?>