<?=Wonder\App\Dependencies::Body()?>

<!-- Custom => (.css, .js) -->
<?php include $ROOT."/custom/utility/backend/body-end.php";?>

<script>

    window.addEventListener('load', () => {

        const theme = (localStorage.theme === 'dark' || localStorage.theme === 'light')
            ? localStorage.theme
            : 'light';

        if (localStorage.theme != theme) { bootstrapTheme(theme); }

        setUpPage();
        <?=alert()?>

    });

</script>
