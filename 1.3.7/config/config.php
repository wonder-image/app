<?php

    require_once $ROOT_APP."/config/env.php";
    require_once $ROOT_APP."/config/array.php";
    require_once $ROOT_APP."/config/alert.php";
    require_once $ROOT_APP."/config/characters.php";
    require_once $ROOT_APP."/config/fpdf.php";

    # Configurazioni CUSTOM
    require_once $ROOT."/custom/config/config.php";

    # Database
        $DB->hostname = $_ENV['DB_HOSTNAME'];
        $DB->username = $_ENV['DB_USERNAME'];
        $DB->password = $_ENV['DB_PASSWORD'];
        $DB->database = explode(',', $_ENV['DB_DATABASE']);

        # Trasformo in un array leggibile i dettagli passati dal file .env 
            $DATABASE_ARRAY = [];
                
            if (count($DB->database) > 1) {

                foreach ($DB->database as $k => $v) {
                    
                    $A_VALUES = explode(':', str_replace(' ', '', $v));
                    $DATABASE_ARRAY[$A_VALUES[0]] = $A_VALUES[1];

                }

                $DB->database = $DATABASE_ARRAY;

            } else {

                $DATABASE = explode(':', str_replace(' ', '', $DB->database[0]));
                $DATABASE_ARRAY['main'] = isset($DATABASE[1]) ? $DATABASE[1] : $DB->database[0];

                $DB->database = $DATABASE_ARRAY;

            }

            $DB->database['information_schema'] = "INFORMATION_SCHEMA";

        #
        
    # Path
        $PATH->site = $_ENV['APP_URL'];
        $PATH->backend = $PATH->site."/backend";
        $PATH->app = $PATH->site."/vendor/wonder-image/app/".$APP_VERSION;
        
        $PATH->lib = $PATH->app."/lib";

        $PATH->appAssets = $PATH->app."/assets";

        $PATH->appApi = $PATH->app."/api";
        $PATH->appCss = $PATH->appAssets."/css";
        $PATH->appJs = $PATH->appAssets."/js";

        $PATH->assets = $PATH->site."/assets/".$_ENV['ASSETS_VERSION'];
        $PATH->api = $PATH->site."/api";
        $PATH->css = $PATH->assets."/css";
        $PATH->js = $PATH->assets."/js";

        $PATH->upload = $PATH->site."/assets/upload";
        $PATH->temp = $PATH->site."/assets/temp";

        $PATH->rUpload = $ROOT."/assets/upload";
        $PATH->rTemp = $ROOT."/assets/temp";

        $PATH->logo = $PATH->upload."/logos/Logo.png";
        $PATH->logoWhite = $PATH->upload."/logos/Logo-White.png";
        $PATH->logoBlack = $PATH->upload."/logos/Logo-Black.png";
        $PATH->logoIcon = $PATH->upload."/logos/Logo-Icon.png";
        $PATH->favicon = $PATH->site."/favicon.ico";
        $PATH->appIcon = $PATH->upload."/logos/App-Icon.png";
        
    # Loghi backend
        $DEFAULT->BeLogoBlack = $PATH->app.'/assets/logos/Wonder-Image.png';
        $DEFAULT->BeLogoWhite = $PATH->app.'/assets/logos/Wonder-Image-White.png';
        $DEFAULT->BeFavicon = 'https://www.wonderimage.it/favicon.ico';

    # Colori utenti
        $DEFAULT->colorUser = [
            'blue' => [
                'name' => 'Blu',
                'color' => '#0000ff',
                'contrast' => '#ffffff',
                'active' => true
            ],
            'green' => [
                'name' => 'Verde',
                'color' => '#008000',
                'contrast' => '#ffffff',
                'active' => true
            ],
            'purple' => [
                'name' => 'Viola',
                'color' => '#800080',
                'contrast' => '#ffffff',
                'active' => true
            ],
            'red' => [
                'name' => 'Rosso',
                'color' => '#FF0000',
                'contrast' => '#000000',
                'active' => true
            ],
            'yellow' => [
                'name' => 'Giallo',
                'color' => '#FFFF00',
                'contrast' => '#000000',
                'active' => true
            ],
            'pink' => [
                'name' => 'Rosa',
                'color' => '#FFC0CB',
                'contrast' => '#000000',
                'active' => true
            ],
            'orange' => [
                'name' => 'Arancione',
                'color' => '#FFA500',
                'contrast' => '#000000',
                'active' => true
            ],
            'turquoise' => [
                'name' => 'Turchese',
                'color' => '#40E0D0',
                'contrast' => '#000000',
                'active' => true
            ],
            'gold' => [
                'name' => 'Oro',
                'color' => '#FFD700',
                'contrast' => '#000000',
                'active' => true
            ],
            'silver' => [
                'name' => 'Argento',
                'color' => '#C0C0C0',
                'contrast' => '#000000',
                'active' => true
            ]
        ];

    # Default
        $DEFAULT->image = $PATH->assets."/images/Default.png";
        
        $DEFAULT->font = [
            [
                "name" => "Roboto",
                "link" => "https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap",
                "font-family" => "'Roboto', sans-serif"
            ],
            [
                "name" => "Montserrat",
                "link" => "https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap",
                "font-family" => "'Montserrat', sans-serif"
           ] 
        ];

        $DEFAULT->color = [
            [
                "var" => "primary",
                "name" => "Primario",
                "color" => "#000000",
                "contrast" => "#ffffff"
            ],
            [
                "var" => "secondary",
                "name" => "Secondario",
                "color" => "#ffffff",
                "contrast" => "#000000"
            ],
            [
                "var" => "success",
                "name" => "Successo",
                "color" => "#28a745",
                "contrast" => "#ffffff"
            ],
            [
                "var" => "info",
                "name" => "Informazione",
                "color" => "#17a2b8",
                "contrast" => "#ffffff"
            ],
            [
                "var" => "danger",
                "name" => "Pericolo",
                "color" => "#dc3545",
                "contrast" => "#ffffff"
            ],
            [
                "var" => "dark",
                "name" => "Scuro",
                "color" => "#343a40",
                "contrast" => "var(--light-color)"
            ],
            [
                "var" => "light",
                "name" => "Chiaro",
                "color" => "#f8f9fa",
                "contrast" => "var(--dark-color)"
            ],
            [
                "var" => "gray",
                "name" => "Grigio",
                "color" => "#eeeeee",
                "contrast" => "#000000"
            ],
            [
                "var" => "black",
                "name" => "Nero",
                "color" => "#000000",
                "contrast" => "#ffffff"
            ],
            [
                "var" => "white",
                "name" => "Bianco",
                "color" => "#ffffff",
                "contrast" => "#000000"
            ]
        ];

        $DEFAULT->appIcon = [ '196',  '180', '152', '144', '120', '114', '76', '72', '57', '32', '16' ];

    require_once $ROOT_APP."/config/permissions.php";
    require_once $ROOT_APP."/config/table.php";

?>