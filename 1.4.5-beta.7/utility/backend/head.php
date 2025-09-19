<script>
    
    if (localStorage.theme != 'dark' && localStorage.theme != 'light') {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    }

    document.querySelector("html").setAttribute("data-bs-theme", localStorage.theme);

</script>

<!-- Inizio icone -->

    <link rel="shortcut icon" href="<?=$DEFAULT->BeFavicon?>" type="image/x-icon">

<!-- Fine icone -->

<!-- Inizio Config  -->

    <script>

        const pathSite = '<?=$PATH->site?>';
        const pathApp = '<?=$PATH->app?>';
        var NO_INTERNET_ALERT = null;

    </script>

<!-- Fine Config  -->

<!-- Inizio librerie -->

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

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

<!-- Custom => (.css, .js) -->
<?php include $ROOT.'/custom/utility/backend/head.php';?>