<?php


    class Table {

        public $title = false;
        public $titleNResult = false;

        public $buttonAdd = [
            'visible' => false,
            'icon' => '',
            'name' => '',
            'href' => ''
        ];

        public $text = [
            'titleS' => 'riga',
            'titleP' => 'righe',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'piena',
            'empty' => 'vuota',
            'this' => 'questa'
        ];

        public $buttonCustom = [];

        public $searchFields = [];

        public $filter = "";
        public $filterCustom = [];

        private $columns = [];

        public $table = "";
        public $database = "main";

        private $mysqli = "";
        private $query = "";
        private $queryCustom = "";
        private $id = [];

        function __construct( $table, $mysqli ) {

            $this->table = $table;
            $this->mysqli = $mysqli;
            
            $this->id = [
                'table' => $this->createId('table'),
                'filter_container' => $this->createId('filter_container'),
                'search_input' => $this->createId('search_input')
            ];

        }

        public function title( bool $bool = false ) { $this->title = $bool; }
        public function titleNResult( bool $bool = false ) { $this->titleNResult = $bool; }

        public function buttonAdd( string $href, string $name = 'Aggiungi', string $icon = '<i class="bi bi-plus-lg"></i>') { 

            $this->buttonAdd['visible'] = empty($name) ? false : true;
            $this->buttonAdd['name'] = $name;
            $this->buttonAdd['href'] = $href;
            $this->buttonAdd['icon'] = $icon;

        }

        public function buttonCustom( array $button ) { $this->buttonCustom = $button; }
        
        public function searchFields( array $fields ) { $this->searchFields = $fields; }

        public function query( string $query ) { $this->query .= $query; }

        public function filter( string $filter ) { $this->filter = $filter; }

        private function createId( $id ) { return $this->table.'__'.$id; }

        /**
         * Undocumented function
         *
         * @param [type] $column
         * @param array $array
         * @param [type] $input = select || checkbox || radio || tree
         * @param bool $search
         * @return void
         */
        public function addFilter( $label, $column, array $array, $input = 'select', bool $search = false ) {

            array_push(
                $this->filterCustom, [
                    'label' => $label,
                    'column' => $column,
                    'array' => $array,
                    'input' => $input,
                    'search' => $search
                ]
            );

        }

        /**
         * Undocumented function
         *
         * @param [type] $label
         * @param [type] $column
         * @param [type] $orderable
         * @param [type] $class
         * @param string $device = mobile || tablet || desktop
         * @param int string $width = 
         * @return void
         */
        public function addColumn( $label, $column, bool $orderable = false, $class = '', $hiddenDevice = '', $width = 'auto') {

            if (empty($hiddenDevice)) {
                $class .= ' all';
            } else if ($hiddenDevice == 'mobile') {
                $class .= ' not-mobile';
            } else if ($hiddenDevice == 'tablet') {
                $class .= ' not-tablet';
            } else if ($hiddenDevice == 'desktop') {
                $class .= ' not-desktop';
            }

            if (empty($width)) {
                $width = 'auto';
            } else if ($width == 'little') {
                $width = '30px';
            } else if ($width == 'medium') {
                $width = '120px';
            } else if ($width == 'big') {
                $width = '180px';
            }

            array_push($this->columns, [
                'name' => $column,
                'title' => $label,
                'orderable' => $orderable,
                'className' => $class,
                'width' => $width
            ]);

        }

        public function text( $titleS = 'riga', $titleP = 'righe', $last = 'ultime', $all = 'tutte', $article = 'le', $full = 'piena', $empty = 'vuota', $thiss = 'questa' ) {

            $this->text['titleS'] = $titleS;
            $this->text['titleP'] = $titleP;
            $this->text['last'] = $last;
            $this->text['all'] = $all;
            $this->text['article'] = $article;
            $this->text['full'] = $full;
            $this->text['empty'] = $empty;
            $this->text['this'] = $thiss;

        }

        private function rowHeader() {

            $TITLE_HTML = '';

            if ($this->title) {

                $col = ($this->buttonAdd['visible']) ? 'col-8' : 'col-12';

                $TITLE_HTML .= '<div class="'.$col.'"><h3>'.$this->title.'</h3>';

                if ($this->titleNResult) { $TITLE_HTML .= '<figcaption class="text-muted"> Risultati: <span id="wi-table-result"></span> </figcaption>'; }

                $TITLE_HTML .= '</div>';

            } else if ($this->titleNResult) {

                $col = ($this->buttonAdd['visible']) ? 'col-8' : 'col-12';

                $TITLE_HTML .= '<div class="'.$col.'">';
                $TITLE_HTML .= '<figcaption class="text-muted"> Risultati: <span id="wi-table-result"></span> </figcaption>';
                $TITLE_HTML .= '</div>';

            }

            $BUTTON_ADD_HTML = '';
            
            if ($this->buttonAdd['visible']) {

                $col = ($this->title) ? 'col-4' : 'col-12';
                $href = $this->buttonAdd['href'];
                $name = $this->buttonAdd['name'];
                $icon = $this->buttonAdd['icon'];

                $BUTTON_ADD_HTML .= '<div class="'.$col.'"><a href="'.$href.'" type="button" class="btn btn-dark btn-sm float-end"> '.$icon.' '.$name.' </a></div>';

            }

            $FILTER_HTML = '';
            $FILTER_USED = 0;

            if (!empty($this->filterCustom)) {

                $FILTER_HTML .= '<div id="'.$this->id['filter_container'].'" class="col-12 collapse border-top border-bottom">';
                $FILTER_HTML .= '<form action="" method="get" onsubmit="loadingSpinner()" class="my-3">';
                $FILTER_HTML .= '<div class="col-12">';
                $FILTER_HTML .= '<div class="container p-0" style="max-width: 100%;">';
                $FILTER_HTML .= '<div class="row g-3">';

                foreach ($this->filterCustom as $key => $options) {

                    $FILTER_HTML .= '<div class="col-3">';

                    $name = $this->createId($options['column']);
                    $value = isset($_GET[$name]) ? $_GET[$name] : null;

                    if ($options['input'] == 'select') {
                        $FILTER_HTML .= select($options['label'], $name, $options['array'], 'old', null, $value);
                    } else if ($options['input'] == 'checkbox') {
                        $FILTER_HTML .= check($options['label'], $name, $options['array'], null, 'checkbox', $options['search'], $value);
                    } else if ($options['input'] == 'radio') {
                        $FILTER_HTML .= check($options['label'], $name, $options['array'], null, 'radio', $options['search'], $value);
                    } else if ($options['input'] == 'tree') {
                        $FILTER_HTML .= checkTree($options['label'], $name, $options['array'], null, 'checkbox', true, $value);
                    }

                    $FILTER_HTML .= '</div>';

                }

                $FILTER_HTML .= '<div class="col-3">';
                $FILTER_HTML .= '<button type="submit" class="btn btn-dark btn-sm"> <i class="bi bi-search"></i> Applica filtri </button>';
                $FILTER_HTML .= '</div>';

                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</form>';
                $FILTER_HTML .= '</div>';
                
            }

            $FILTER_BUTTON_HTML = '';

            if (!empty($this->filterCustom)) {

                $FILTER_BUTTON_HTML .= '<div class="col-auto pe-0">';
                $FILTER_BUTTON_HTML .= '<button type="button" class="position-relative btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#'.$this->id['filter_container'].'" aria-expanded="false">';
                $FILTER_BUTTON_HTML .= '<i class="bi bi-filter"></i> Filtri';
                $FILTER_BUTTON_HTML .= ($FILTER_USED > 0) ? '<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary" style="--bs-badge-font-size: 0.7em;">'.$FILTER_USED.' <span class="visually-hidden">unread messages</span></span>' : '';
                $FILTER_BUTTON_HTML .= '</button>';
                $FILTER_BUTTON_HTML .= '</div>';
                
            }

            $SEARCH_HTML = '';

            if (!empty($this->searchFields)) {
                
                $SEARCH_HTML .= '<div class="col-4 me-auto">';
                $SEARCH_HTML .= '<div class="input-group input-group-sm">';
                $SEARCH_HTML .= '<span class="input-group-text user-select-none">Cerca: </span>';
                $SEARCH_HTML .= '<input type="text" class="form-control" id="'.$this->id['search_input'].'">';
                $SEARCH_HTML .= '</div>';
                $SEARCH_HTML .= '</div>';

            }

            $RETURN = "";
            $RETURN .= $TITLE_HTML;
            $RETURN .= $BUTTON_ADD_HTML;
            $RETURN .= $FILTER_BUTTON_HTML;
            $RETURN .= $SEARCH_HTML;
            $RETURN .= $FILTER_HTML;

            return $RETURN;

        }

        private function rowTable() {

            $RETURN = '';
            $RETURN .= '<div class="col-12">';
            $RETURN .= '<table id="'.$this->id['table'].'" class="table table-hover w-100">';
            $RETURN .= '<thead></thead>';
            $RETURN .= '<tbody class="table-group-divider"></tbody>';
            $RETURN .= '</table>';
            $RETURN .= '</div>';

            return $RETURN;

        }

        public function generate( $card = true ) {

            $HTML = '<wi-card class="col-12">';
            $HTML .= $this->rowHeader();
            $HTML .= $this->rowTable();
            $HTML .= '</wi-card>';

            return $HTML;
            
        }


    }