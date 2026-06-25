<?=Wonder\App\Dependencies::Body()?>

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
