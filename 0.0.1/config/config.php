<?php

    require_once $ROOT_APP."/config/env.php";
    require_once $ROOT_APP."/config/array.php";
    require_once $ROOT_APP."/config/alert.php";

    # Configurazioni CUSTOM
    require_once $ROOT."/custom/config/config.php";

    // Database
        $DB->hostname = $_ENV['DB_HOSTNAME'];
        $DB->username = $_ENV['DB_USERNAME'];
        $DB->password = $_ENV['DB_PASSWORD'];
        $DB->database = $_ENV['DB_DATABASE'];
        
    // Path
        $PATH->site = $_ENV['APP_SITE'];
        $PATH->backend = $PATH->site."/backend";
        $PATH->app = $PATH->site."/vendor/wonder-image/app/".$VERSION;

        $PATH->lib = $PATH->app."/lib";

        $PATH->appAssets = $PATH->app."/assets/";
        $PATH->appCss = $PATH->appAssets."/css/";
        $PATH->appJs = $PATH->appAssets."/js/";

        $PATH->assets = $PATH->site."/assets/".$_ENV['ASSETS_VERSION'];
        $PATH->css = $PATH->assets."/css";
        $PATH->js = $PATH->assets."/js";

        $PATH->upload = $PATH->site."/assets/upload";
        $PATH->temp = $PATH->site."/assets/temp";

        $PATH->rUpload = $ROOT."/assets/upload";
        $PATH->rTemp = $ROOT."/assets/temp";

        $PATH->logo = $PATH->assets."/logo/".$_ENV['APP_LOGO'];
        $PATH->logoWhite = $PATH->assets."/logos/".$_ENV['APP_LOGO_WHITE'];
        $PATH->icon = $PATH->assets."/logo/".$_ENV['APP_ICON'];
     
    // Default
        $DEFAULT->image = $PATH->assets."/images/Default.png";


    require_once $ROOT_APP."/config/permissions.php";
    require_once $ROOT_APP."/config/table.php";

?>