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
        $PATH->site = $_ENV['APP_URL'];
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
        $DEFAULT->font = [
            [
                 "name" => "Roboto",
                 "link" => "https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap",
                 "font-family" => "'Roboto', sans-serif"
            ] 
        ];

        $DEFAULT->color = [
        [
            "var" => "--white-color",
            "name" => "Bianco",
            "color" => "#ffffff"
        ],
        [
            "var" => "--black-color",
            "name" => "Nero",
            "color" => "#000000"
        ],
        [
            "var" => "--light-color",
            "name" => "Chiaro",
            "color" => "#f8f9fa"
        ],
        [
            "var" => "--dark-color",
            "name" => "Scuro",
            "color" => "#343a40"
        ],
        [
            "var" => "--danger-color",
            "name" => "Pericolo",
            "color" => "#dc3545"
        ],
        [
            "var" => "--info-color",
            "name" => "Informazione",
            "color" => "#17a2b8"
        ],
        [
            "var" => "--success-color",
            "name" => "Successo",
            "color" => "#28a745"
        ],
        [
            "var" => "--tx-color",
            "name" => "Testo",
            "color" => "#000000"
        ],
        [
            "var" => "--bg-color",
            "name" => "Sfondo",
            "color" => "#ffffff"
        ],
        [
            "var" => "--secondary-color",
            "name" => "Secondario",
            "color" => "#ffffff"
        ],
        [
            "var" => "--primary-color",
            "name" => "Primario",
            "color" => "#000000"
        ] 
        ];

    require_once $ROOT_APP."/config/permissions.php";
    require_once $ROOT_APP."/config/table.php";

?>