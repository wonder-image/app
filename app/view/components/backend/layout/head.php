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

<link rel="shortcut icon" href="<?=e($DEFAULT->BeFavicon ?? '')?>" type="image/x-icon">

<script>

    const pathSite = <?=json_encode((string) ($PATH->site ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const pathApp = <?=json_encode((string) ($PATH->app ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const NO_INTERNET_ALERT = <?=json_encode((string) alertTheme(801), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

    const API_TOKEN = <?=json_encode((string) Wonder\App\Credentials::appToken(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_API_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->gcp_client_api_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_SITE_KEY = <?=json_encode((string) Wonder\App\Credentials::api()->g_recaptcha_site_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    const GOOGLE_PLACE_ID = <?=json_encode((string) Wonder\App\Credentials::api()->g_maps_place_id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<?=Wonder\App\Dependencies::Head()?>

<script>
    
    TranslationProvider.init(
        <?=json_encode(Wonder\Localization\TranslationProvider::$translations)?>,
        <?=json_encode(Wonder\Localization\TranslationProvider::$defaultTranslations)?>
    );

</script>
