<?php 

    $TAG_MANAGER = sqlSelect('analytics', ['id' => '1'], 1)->row['tag_manager'];

    if ($TAG_MANAGER != '') { 

?>
<!-- Google Tag Manager -->
<script>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?=$TAG_MANAGER?>');
</script>
<!-- End Google Tag Manager -->
<?php } ?>

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

<!-- Fine SEO -->

<!-- Start fundamental file  -->

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css">

    <!-- Animazioni AOS -->
    <link id="aos-css" href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Swiper.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">

    <!-- Fancybox -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">

    <!-- Video.js -->
    <link href="https://unpkg.com/video.js@7/dist/video-js.min.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/city/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/fantasy/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/forest/index.css" rel="stylesheet">
    <link href="https://unpkg.com/@videojs/themes@1/dist/sea/index.css" rel="stylesheet">

    <!-- Typed.js -->
    <script src="https://unpkg.com/typed.js@2.0.15/dist/typed.umd.js"></script>

    <!-- Autonumeric -->
    <script src="<?=$PATH->lib?>/autonumeric/4.8.1/autoNumeric.min.js"></script>

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
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/hamburger.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/header/mobile-nav.css">

    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/alert.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/modal.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/button.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/dropdown.css">

    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/input.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/date.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/checkbox.css">
    <link rel="stylesheet" href="<?=$PATH->appCss?>/frontend/plugin/form/select.css">

<!-- End fundamental file  -->

<?php include $ROOT."/custom/utility/frontend/head.php"; ?>