<?php

    $DEFAULT_NAV_TOP = [
        [
            'title' => 'Home',
            'folder' => 'home',
            'icon' => 'bi-house-door',
            'file' => $PERMITS['backend']['links']['home'],
            'authority' => [],
            'subnavs' => []
        ]
    ];

    $DEFAULT_NAV_BOTTOM = [
        [
            'title' => 'Set Up',
            'folder' => 'set-up',
            'icon' => 'bi-gear',
            'authority' => ['admin'],
            'subnavs' => [
                [
                    'title' => 'Dati aziendali',
                    'folder' => 'config-app/corporate-data',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Logo',
                    'folder' => 'config-app/logos',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Seo',
                    'folder' => 'config-app/seo',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Utenti',
                    'folder' => 'config-app/user',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Utenti API',
                    'folder' => 'config-app/api-users',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Analitica',
                    'folder' => 'config-app/analytics',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Credenziali',
                    'folder' => 'config-app/credentials',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Editor',
                    'folder' => 'config-app/configuration-file',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Errori SQL',
                    'folder' => 'config-app/sql-error',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Download',
                    'folder' => 'config-app/sql-download',
                    'file' => '',
                    'authority' => ['admin']
                ]
            ]
        ], [
            'title' => 'Stile',
            'folder' => 'css',
            'icon' => 'bi-award',
            'authority' => ['admin'],
            'subnavs' => [
                [
                    'title' => 'Default',
                    'folder' => 'config-css/default',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Font',
                    'folder' => 'config-css/font',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Colori',
                    'folder' => 'config-css/color',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Input',
                    'folder' => 'config-css/input',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Modal',
                    'folder' => 'config-css/modal',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Dropdown',
                    'folder' => 'config-css/dropdown',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Alert',
                    'folder' => 'config-css/alert',
                    'file' => '',
                    'authority' => ['admin']
                ]
            ]
        ]
    ];

    # Dipendenze
    Wonder\App\Dependencies::jquery()
        ::jqueryPlugin()
        ::moment()
        ::bootstrap()
        ::bootstrapIcons()
        ::bootstrapDatepicker()
        ::jszip()
        ::datatables()
        ::quilljs()
        ::editorjs()
        ::filepond()
        ::autonumeric()
        // ::chartjs()
        // ::fullcalendar()
        // ::swiperjs()
        // ::fancyapps()
        ::jstree()
        ::select2()
        ::wiBackend();

    include $ROOT.'/custom/utility/backend/set-up.php';
    
    $NAV_BACKEND = array_merge($DEFAULT_NAV_TOP, $NAV_BACKEND, $DEFAULT_NAV_BOTTOM);