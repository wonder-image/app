<?=Wonder\App\Dependencies::Body()?>

<!-- Custom => (.css, .js) -->
<?php
$customBackendBodyEnd = $ROOT."/custom/utility/backend/body-end.php";
if (file_exists($customBackendBodyEnd)) {
    include $customBackendBodyEnd;
}
?>

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
