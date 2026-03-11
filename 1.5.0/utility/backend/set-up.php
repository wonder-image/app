<?php

    \Wonder\App\Theme::set('bootstrap');

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
            'title' => 'Media',
            'folder' => 'media',
            'icon' => 'bi-image',
            'authority' => ['admin'],
            'subnavs' => [
                [
                    'title' => 'Logo',
                    'folder' => 'app/media/logos',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Immagini',
                    'folder' => 'app/media/images',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Icone',
                    'folder' => 'app/media/icons',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Documenti',
                    'folder' => 'app/media/documents',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Upload di massa',
                    'folder' => 'app/media/upload-massive',
                    'file' => '',
                    'authority' => ['admin']
                ]
            ]
        ], [
            'title' => 'Set Up',
            'folder' => 'set-up',
            'icon' => 'bi-gear',
            'authority' => ['admin'],
            'subnavs' => [
                [
                    'title' => 'Dati aziendali',
                    'folder' => 'app/config/corporate-data',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Seo',
                    'folder' => 'app/config/seo',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Documenti legali',
                    'folder' => 'app/config/legal-documents',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Utenti',
                    'folder' => 'app/config/user',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Utenti API',
                    'folder' => 'app/config/api-users',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Analitica',
                    'folder' => 'app/config/analytics',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Credenziali',
                    'folder' => 'app/config/credentials',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Editor',
                    'folder' => 'app/config/configuration-file',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Errori SQL',
                    'folder' => 'app/config/sql-error',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Download',
                    'folder' => 'app/config/sql-download',
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
                    'folder' => 'app/css/default',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Font',
                    'folder' => 'app/css/font',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Colori',
                    'folder' => 'app/css/color',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ], [
                    'title' => 'Input',
                    'folder' => 'app/css/input',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Modal',
                    'folder' => 'app/css/modal',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Dropdown',
                    'folder' => 'app/css/dropdown',
                    'file' => '',
                    'authority' => ['admin']
                ], [
                    'title' => 'Alert',
                    'folder' => 'app/css/alert',
                    'file' => '',
                    'authority' => ['admin']
                ]
            ]
        ], [
            'title' => 'Log',
            'folder' => 'log',
            'icon' => 'bi-ear',
            'authority' => [ 'admin', 'administrator' ],
            'subnavs' => [
                [
                    'title' => 'Accessi Utente',
                    'folder' => 'app/log/auth-users',
                    'file' => 'list.php',
                    'authority' => [ 'admin', 'administrator' ]
                ], [
                    'title' => 'Email',
                    'folder' => 'app/log/email',
                    'file' => 'list.php',
                    'authority' => [ 'admin', 'administrator' ]
                ], [
                    'title' => 'Consensi',
                    'folder' => 'app/log/consent',
                    'file' => 'list.php',
                    'authority' => [ 'admin', 'administrator' ]
                ]
            ]
        ], 
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
