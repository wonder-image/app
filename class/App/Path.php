<?php

    namespace Wonder\App;

    class Path {

        public $site = APP_URL;
        public $root = ROOT;
        public $assetsVersion = ASSETS_VERSION;
        public $appVersion = APP_VERSION;

        public const PACKAGE = APP_URL.'/vendor/wonder-image/app';
        public const APP = self::PACKAGE.'/app';
        public const APP_RESOURCES = self::PACKAGE.'/resources';
        public const APP_ASSETS = self::APP_RESOURCES.'/assets';
        public const APP_API = self::APP.'/api';
        public const ASSETS = APP_URL.'/assets/'.ASSETS_VERSION;
        public const UPLOAD = APP_URL.'/assets/upload';

        public $backend = APP_URL.'/backend';

        public $package = self::PACKAGE;
        public $app = self::APP;
        public $appResources = self::APP_RESOURCES;
        public $appApi = self::APP_API;
        public $appAssets = self::APP_ASSETS.'';
        public $appCss = self::APP_ASSETS.'/css';
        public $appJs = self::APP_ASSETS.'/js';

        public $api = APP_URL.'/api';
        public $assets = self::ASSETS;
        public $css = self::ASSETS.'/css';
        public $js = self::ASSETS.'/js';


        public $upload = APP_URL.'/assets/upload';
        public $temp = APP_URL.'/storage/tmp';

        public $rUpload = ROOT.'/assets/upload';
        public $rTemp = ROOT.'/storage/tmp';

        public $logo = self::UPLOAD.'/logos/Logo.png';
        public $logoWhite = self::UPLOAD.'/logos/Logo-White.png';
        public $logoBlack = self::UPLOAD.'/logos/Logo-Black.png';
        public $logoIcon = self::UPLOAD.'/logos/Logo-Icon.png';
        public $favicon = self::UPLOAD.'/favicon.ico';
        public $appIcon = self::UPLOAD.'/logos/App-Icon.png';

        public $apiDT = self::APP_API.'/backend/list/table.php'; # Api DataTable

    }
