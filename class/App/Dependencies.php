<?php

    namespace Wonder\App;

    class Dependencies {

        public $endpoint = 'https://cdn.jsdelivr.net/npm/wonder-image@';

        public $dependencies = [
            'jquery' => [
                'name' => 'jQuery',
                'site' => 'https://jquery.com',
                'load' => true,
                'files' => [
                    'head' => [
                        '/dist/lib/jquery/jquery.js'
                    ]
                ]
            ],
            'moment' => [
                'name' => 'Moment.js',
                'site' => 'https://momentjs.com',
                'load' => true,
                'files' => [
                    'head' => [
                        '/dist/lib/moment/moment.js'
                    ]
                ]
            ],
            'jquery-plugin' => [
                'name' => 'JQuery Plugin',
                'site' => 'https://plugins.jquery.com',
                'load' => true,
                'files' => [
                    'head' => [
                        '/dist/lib/jquery/jquery-plugin.js',
                        '/dist/lib/jquery/jquery-plugin.css'
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
                'load' => true,
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
                'load' => true,
                'files' => [
                    'head' => [
                        '/dist/frontend/head.js',
                        '/dist/frontend/head.css'
                    ],
                    'body' => [
                        '/dist/frontend/body-end.js',
                    ]
                ]
            ]
        ];

        public function __construct($version) { $this->endpoint .= $version; }

        private function set($key, bool $value): Dependencies { $this->dependencies[$key]['load'] = $value; return $this; }

        public function jquery(bool $value = true) { return $this->set('jquery', $value); }
        public function moment(bool $value = true) { return $this->set('moment', $value); }
        public function jqueryPlugin(bool $value = true) { return $this->set('jquery-plugin', $value); }
        public function bootstrapIcons(bool $value = true) { return $this->set('bootstrap-icons', $value); }
        public function swiper(bool $value = true) { return $this->set('swiper', $value); }
        public function swiperPlugin(bool $value = true) { return $this->set('swiper-plugin', $value); }
        public function fancyapps(bool $value = true) { return $this->set('fancyapps', $value); }
        public function videojs(bool $value = true) { return $this->set('videojs', $value); }
        public function autonumeric(bool $value = true) { return $this->set('autonumeric', $value); }
        public function rellax(bool $value = true) { return $this->set('rellax', $value); }
        public function vivus(bool $value = true) { return $this->set('vivus', $value); }
        public function wiLib(bool $value = true) { return $this->set('wi-lib', $value); }
        public function wiFrontend(bool $value = true) { return $this->set('wi-frontend', $value); }

        public function generate($container): string {

            $RETURN = "";

            foreach ($this->dependencies as $key => $value) {
                
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

        public function Head(): string{ return $this->generate('head'); }
        
        public function Body(): string{ return $this->generate('body'); }

    }