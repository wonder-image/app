<?php

    include $ROOT.'/custom/utility/backend/set-up.php';

    $DEFAULT_NAV_TOP = [
        [
            'title' => 'Home',
            'folder' => 'home',
            'icon' => 'bi-house-door',
            'file' => '',
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
                    'folder' => 'corporate-data',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Seo',
                    'folder' => 'seo',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Analitica',
                    'folder' => 'analytics',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Utenti',
                    'folder' => 'user',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Errori SQL',
                    'folder' => 'error',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Download',
                    'folder' => 'download',
                    'file' => '',
                    'authority' => ['admin']
                ],
            ]
        ],
        [
            'title' => 'Stile',
            'folder' => 'set-up',
            'icon' => 'bi-award',
            'authority' => ['admin'],
            'subnavs' => [
                [
                    'title' => 'Default',
                    'folder' => 'css-default',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Font',
                    'folder' => 'css-font',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Colori',
                    'folder' => 'css-color',
                    'file' => 'list.php',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Testi',
                    'folder' => 'css-text',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Bottoni',
                    'folder' => 'css-button',
                    'file' => '',
                    'authority' => ['admin']
                ],
                [
                    'title' => 'Input',
                    'folder' => 'css-input',
                    'file' => '',
                    'authority' => ['admin']
                ],
            ]
        ]
    ];

    $NAV_BACKEND = array_merge($DEFAULT_NAV_TOP, $NAV_BACKEND, $DEFAULT_NAV_BOTTOM);

?>