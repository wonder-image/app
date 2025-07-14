<?php

    namespace Wonder\App;

    class Dependencies {

        public $endpoint = APP_URL.'/node_modules/wonder-image';

        public static array $dependencies = [
            'jquery' => [
                'name' => 'jQuery',
                'site' => 'https://jquery.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/jquery/jquery.js'
                    ]
                ]
            ],
            'moment' => [
                'name' => 'Moment.js',
                'site' => 'https://momentjs.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/moment/moment.js'
                    ]
                ]
            ],
            'jquery-plugin' => [
                'name' => 'JQuery Plugin',
                'site' => 'https://plugins.jquery.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/jquery/jquery-plugin.js',
                        '/dist/lib/jquery/jquery-plugin.css'
                    ]
                ]
            ],
            'bootstrap' => [
                'name' => 'Bootstrap',
                'site' => 'https://getbootstrap.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/bootstrap/bootstrap.css'
                    ],
                    'body' => [
                        '/dist/lib/bootstrap/bootstrap.js',
                    ]
                ]
            ],
            'bootstrap-icons' => [
                'name' => 'Bootstrap Icons',
                'site' => 'https://icons.getbootstrap.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/bootstrap/bootstrap-icons.css'
                    ]
                ]
            ],
            'bootstrap-datepicker' => [
                'name' => 'Bootstrap Datepicker',
                'site' => 'https://bootstrap-datepicker.readthedocs.io/',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/bootstrap/bootstrap-datepicker.js',
                        '/dist/lib/bootstrap/bootstrap-datepicker.css'
                    ]
                ]
            ],
            'jszip' => [
                'name' => 'JSZip',
                'site' => 'https://stuk.github.io/jszip/',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/jszip/jszip.js'
                    ]
                ]
            ],
            'datatables' => [
                'name' => 'DataTables',
                'site' => 'https://datatables.net',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/datatables/datatables.js',
                        '/dist/lib/datatables/datatables.css'
                    ]
                ]
            ],
            'quilljs' => [
                'name' => 'Quill.js',
                'site' => 'https://quilljs.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/quilljs/quill.js',
                        '/dist/lib/quilljs/quill.css'
                    ]
                ]
            ],
            'editorjs' => [
                'name' => 'Editor.js',
                'site' => 'https://editorjs.io',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/editorjs/editor.js'
                    ]
                ]
            ],
            'filepond' => [
                'name' => 'FilePond',
                'site' => 'https://pqina.nl/filepond/',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/filepond/filepond.js',
                        '/dist/lib/filepond/filepond.css'
                    ]
                ]
            ],
            'chartjs' => [
                'name' => 'Chart.js',
                'site' => 'https://www.chartjs.org',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/chartjs/chart.js'
                    ]
                ]
            ],
            'fullcalendar' => [
                'name' => 'FullCalendar',
                'site' => 'https://fullcalendar.io',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/fullcalendar/fullcalendar.js'
                    ]
                ]
            ],
            'jstree' => [
                'name' => 'JsTree',
                'site' => 'https://www.jstree.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/jstree/jstree.js',
                        '/dist/lib/jstree/jstree.css'
                    ]
                ]
            ],
            'select2' => [
                'name' => 'Select2',
                'site' => 'https://select2.org',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/select2/select2.js',
                        '/dist/lib/select2/select2.css'
                    ]
                ]
            ],
            'colorjs' => [
                'name' => 'Color.js',
                'site' => 'https://github.com/luukdv/color.js',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/colorjs/color.js'
                    ]
                ]
            ],
            'swiper' => [
                'name' => 'Swiper.js',
                'site' => 'https://swiperjs.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/swiperjs/swiper.js',
                        '/dist/lib/swiperjs/swiper.css'
                    ]
                ]
            ],
            'swiper-plugin' => [
                'name' => 'Swiper.js Plugin',
                'site' => 'https://swiperjs.com/plugins',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/swiperjs/swiper-plugin.js',
                        '/dist/lib/swiperjs/swiper-plugin.css',
                    ]
                ]
            ],
            'fancyapps' => [
                'name' => 'Fancyapps',
                'site' => 'https://fancyapps.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/fancyapps/fancyapps.js',
                        '/dist/lib/fancyapps/fancyapps.css',
                    ]
                ]
            ],
            'videojs' => [
                'name' => 'Video.js',
                'site' => 'https://videojs.com',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/videojs/video.js',
                        '/dist/lib/videojs/video.css',
                    ]
                ]
            ],
            'autonumeric' => [
                'name' => 'AutoNumeric.js',
                'site' => 'https://autonumeric.org',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/autonumeric/autonumeric.js',
                    ]
                ]
            ],
            'rellax' => [
                'name' => 'Rellax',
                'site' => 'https://yaireo.github.io/rellax',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/rellax/rellax.js'
                    ]
                ]
            ],
            'vivus' => [
                'name' => 'Vivus.js',
                'site' => 'https://maxwellito.github.io/vivus',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/lib/vivus/vivus.js'
                    ]
                ]
            ],
            'wi-lib' => [
                'name' => 'WI - Libraries',
                'site' => 'https://www.wonderimage.it',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/frontend/lib.js',
                        '/dist/frontend/lib.css',
                    ]
                ]
            ],
            'wi-frontend' => [
                'name' => 'WI - Frontend',
                'site' => 'https://www.wonderimage.it',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/frontend/head.js',
                        '/dist/frontend/head.css'
                    ],
                    'body' => [
                        '/dist/frontend/body-end.js',
                    ]
                ]
            ],
            'wi-backend' => [
                'name' => 'WI - Backend',
                'site' => 'https://www.wonderimage.it',
                'load' => false,
                'files' => [
                    'head' => [
                        '/dist/backend/head.js',
                        '/dist/backend/head.css'
                    ],
                    'body' => [
                        '/dist/backend/body-end.js',
                    ]
                ]
            ]
        ];

        
        private function set($key, bool $value): Dependencies 
        { 

            self::$dependencies[$key]['load'] = $value; 
            
            return $this; 
        
        }
        
        public function __call(string $method, array $arguments): self
        {
            $value = $arguments[0] ?? true;

            // Converte il nome del metodo camelCase in snake-case con trattini
            $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $method));

            return $this->set($key, $value);
        }

        public function generate($container): string 
        {

            $RETURN = "";

            foreach (self::$dependencies as $key => $value) {
                
                $files = $value['files'][$container] ?? [];

                if ($value['load'] == true && !empty($files)) {

                    $RETURN .= "\n";
                    $RETURN .= "<!-- {$value['name']} | {$value['site']} -->\n";

                    foreach ($files as $file) {

                        $url = "{$this->endpoint}{$file}";

                        if (substr($url, -2) == 'js') {
                            $RETURN .= "<script src=\"$url\"></script>";
                        } else if (substr($url, -3) == 'css') {
                            $RETURN .= "<link href=\"$url\" rel=\"stylesheet\">";
                        }

                        $RETURN .= "\n";

                    }

                }

            }

            return $RETURN;

        }

        public function Head(): string
        { 

            return $this->generate('head'); 
        
        }
        
        public function Body(): string
        { 
            
            return $this->generate('body'); 
        
        }

    }