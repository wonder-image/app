<?php

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
        