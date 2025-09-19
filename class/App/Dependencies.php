<?php

    namespace Wonder\App;

    /**
        * Classe per gestire le dipendenze.
        * 
        * @method static self jquery(bool $value = true)
        * @method static self moment(bool $value = true)
        * @method static self jqueryPlugin(bool $value = true)
        * @method static self bootstrap(bool $value = true)
        * @method static self bootstrapIcons(bool $value = true)
        * @method static self bootstrapDatepicker(bool $value = true)
        * @method static self jszip(bool $value = true)
        * @method static self datatables(bool $value = true)
        * @method static self quilljs(bool $value = true)
        * @method static self editorjs(bool $value = true)
        * @method static self filepond(bool $value = true)
        * @method static self chartjs(bool $value = true)
        * @method static self fullcalendar(bool $value = true)
        * @method static self jstree(bool $value = true)
        * @method static self select2(bool $value = true)
        * @method static self colorjs(bool $value = true)
        * @method static self swiper(bool $value = true)
        * @method static self swiperPlugin(bool $value = true)
        * @method static self fancyapps(bool $value = true)
        * @method static self videojs(bool $value = true)
        * @method static self autonumeric(bool $value = true)
        * @method static self rellax(bool $value = true)
        * @method static self vivus(bool $value = true)
        * @method static self wiLib(bool $value = true)
        * @method static self wiFrontend(bool $value = true)
        * @method static self wiBackend(bool $value = true)
    */

        
    class Dependencies {

        public static $endpoint = APP_URL.'/node_modules/wonder-image';

        public static array $dependencies = [
            'jquery' => [
                'name' => 'jQuery',
                'site' => 'https://jquery.com',
                'files' => [
                    'head' => [
                        '/dist/lib/jquery/jquery.js'
                    ]
                ]
            ],
            'moment' => [
                'name' => 'Moment.js',
                'site' => 'https://momentjs.com',
                'files' => [
                    'head' => [
                        '/dist/lib/moment/moment.js'
                    ]
                ]
            ],
            'jquery-plugin' => [
                'name' => 'JQuery Plugin',
                'site' => 'https://plugins.jquery.com',
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
                'files' => [
                    'head' => [
                        '/dist/lib/bootstrap/bootstrap-icons.css'
                    ]
                ]
            ],
            'flag-icons' => [
                'name' => 'Flag Icons',
                'site' => 'https://flagicons.lipis.dev',
                'files' => [
                    'head' => [
                        '/dist/lib/lipis/flag-icons.css'
                    ]
                ]
            ],
            'bootstrap-datepicker' => [
                'name' => 'Bootstrap Datepicker',
                'site' => 'https://bootstrap-datepicker.readthedocs.io',
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
                'files' => [
                    'head' => [
                        '/dist/lib/jszip/jszip.js'
                    ]
                ]
            ],
            'datatables' => [
                'name' => 'DataTables',
                'site' => 'https://datatables.net',
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
                'files' => [
                    'head' => [
                        '/dist/lib/editorjs/editor.js'
                    ]
                ]
            ],
            'filepond' => [
                'name' => 'FilePond',
                'site' => 'https://pqina.nl/filepond/',
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
                'files' => [
                    'head' => [
                        '/dist/lib/chartjs/chart.js'
                    ]
                ]
            ],
            'fullcalendar' => [
                'name' => 'FullCalendar',
                'site' => 'https://fullcalendar.io',
                'files' => [
                    'head' => [
                        '/dist/lib/fullcalendar/fullcalendar.js'
                    ]
                ]
            ],
            'jstree' => [
                'name' => 'JsTree',
                'site' => 'https://www.jstree.com',
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
                'files' => [
                    'head' => [
                        '/dist/lib/colorjs/color.js'
                    ]
                ]
            ],
            'swiper' => [
                'name' => 'Swiper.js',
                'site' => 'https://swiperjs.com',
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
                'files' => [
                    'head' => [
                        '/dist/lib/autonumeric/autonumeric.js',
                    ]
                ]
            ],
            'rellax' => [
                'name' => 'Rellax',
                'site' => 'https://yaireo.github.io/rellax',
                'files' => [
                    'head' => [
                        '/dist/lib/rellax/rellax.js'
                    ]
                ]
            ],
            'vivus' => [
                'name' => 'Vivus.js',
                'site' => 'https://maxwellito.github.io/vivus',
                'files' => [
                    'head' => [
                        '/dist/lib/vivus/vivus.js'
                    ]
                ]
            ],
            'wi-lib' => [
                'name' => 'WI - Libraries',
                'site' => 'https://www.wonderimage.it',
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

        protected static array $toLoad = [];

        
        private static function set($key, bool $value): Dependencies 
        { 

            if (!isset(self::$dependencies[$key])) {
                throw new \InvalidArgumentException("La dipendenza '$key' non esiste.");
            }

            if ($value) {
                self::$toLoad[$key] = true;
            } else {
                unset(self::$toLoad[$key]);
            }
            
            return new self();
        
        }
        
        public static function __callStatic(string $method, array $arguments): self
        {
            
            $value = $arguments[0] ?? true;

            // Converte il nome del metodo camelCase in snake-case con trattini
            $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $method));

            return self::set($key, $value);

        }

        public static function generate($container): string 
        {

            $html = "";

            foreach (self::$toLoad as $key => $value) {
                
                $dep = self::$dependencies[$key] ?? null;

                if (!$dep) continue;

                $files = $dep['files'][$container] ?? [];
                if (empty($files)) continue;
                
                $html .= "\n<!-- {$dep['name']} | {$dep['site']} -->\n";

                foreach ($files as $file) {

                    $url = self::$endpoint . $file;

                    if (str_ends_with($file, '.js')) {
                        $html .= "<script src=\"$url\"></script>\n";
                    } elseif (str_ends_with($file, '.css')) {
                        $html .= "<link href=\"$url\" rel=\"stylesheet\">\n";
                    }

                    $html .= "\n";

                }

            }

            return $html;

        }

        public static function Head(): string
        { 

            return self::generate('head'); 
        
        }
        
        public static function Body(): string
        { 
            
            return self::generate('body'); 
        
        }

    }