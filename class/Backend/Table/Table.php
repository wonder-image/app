<?php

    namespace Wonder\Backend\Table;
    
    use Wonder\Sql\Query;
    use Wonder\Backend\Filter\FilterDate;
    use Wonder\Backend\Filter\FilterCustom;

    /**
     * 
     * Funzioni esterne utilizzate:
     *  - sqlTableInfo
     * 
     */
    class Table {

        # Connessione alla tabella
            public $table = "";
            public $database = "";
            private $mysqli = "";
            private $SQL = "";

        # Creazione query
            private $query = "";
            private $queryCustom = "";
            private $queryFilter = "";

            private $orderColumn = "";
            private $orderDirection = "";

            private $orderColumnFilterActive = "";
            private $orderDirectionFilterActive = "";

            private $columns = [];

            public $default = [
                'length' => 10,
                'page' => 0,
                'search' => ''
            ];
            
        # Titolo
            public $title = false;
            public $titleValue = "";
            public $titleNResult = false;

        # Bottoni
            public $buttonAdd = [
                'visible' => false,
                'icon' => '',
                'name' => '',
                'href' => ''
            ];
            public $buttonDownload = [
                'visible' => false,
                'format' => []
            ];
            public $buttonCustom = [];

        # Filtri
            public $searchFields = [];

            private $filterLimit = [
                'visible' => false
            ];

            private $filterDate = [
                'visible' => false,
                'days' => '',
                'column' => ''
            ];

            private $filterSearch = [
                'visible' => false,
                'fields' => []
            ];

            private $filterCustom = [];

        # Endpoint
            public $endpoint = "";
            public $endpointValues = [];

        # Utilità
            private $id = [];
            private $columnId = 0;
            public $url = "";
            public $link = [];
            public $text = [
                'titleS' => 'elemento',
                'titleP' => 'elementi',
                'last' => 'ultimi',
                'all' => 'tutti',
                'article' => 'gli',
                'full' => 'pieno',
                'empty' => 'vuoto',
                'this' => 'questo'
            ];


        function __construct( $table, $connection ) {

            $this->table = $table;
            $this->mysqli = $connection;

            $this->SQL = new Query( $this->mysqli );
            $this->database = $this->SQL->GetDatabase();

            $this->url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            $this->id = [
                'table' => $this->createId('table'),
                'n_result' => $this->createId('n_result'),
                'filter_container' => $this->createId('filter_container'),
                'limit_input' => $this->createId('limit_input'),
                'search_input' => $this->createId('search_input'),
                'page' => $this->createId('page'),
                'length' => $this->createId('length'),
                'search' => $this->createId('search'),
                'order' => $this->createId('order'),
                'order_dir' => $this->createId('order_dir')
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

        public function buttonDownload( bool $visible = false, array $format = [ 'csv' => 'CSV', 'print' => 'Stampa' ] ) { 
            
            $this->buttonDownload['visible'] = $visible; 
            $this->buttonDownload['format'] = $format; 

        }

        public function addButtonCustom( string $value, bool $isHTML, string $action = '', string $color = 'dark' ) { 

            array_push($this->buttonCustom, [
                'value' => $value,
                'is_html' => $isHTML,
                'action' => $action,
                'color' => $color
            ]);
        
        }
        
        public function endpoint( string $endpoint ) { $this->endpoint = $endpoint; }
        public function addEndpointValue( $key, $value ) { $this->endpointValues[$key] = $value; }

        public function addLink( $key, $link ) { $this->link[$key] = $link; }

        public function query( string $query ) { $this->queryCustom = $query; }
        public function queryOrder( string $column, string $direction = 'DESC', string $columnWhenFilterIsActive = null, string $directionWhenFilterIsActive = null ) { 

            $this->orderColumn = $column; 
            $this->orderDirection = $direction;

            # Con i filtri attivi
            $this->orderColumnFilterActive = $columnWhenFilterIsActive == null ? $column : $columnWhenFilterIsActive; 
            $this->orderDirectionFilterActive = $directionWhenFilterIsActive == null ? $direction : $directionWhenFilterIsActive; 

        }

        
        # Filtri
            public function filterDate( bool $visible = false, int $days = 30, string $column = 'creation' ) { 

                $this->filterDate['visible'] = $visible; 
                $this->filterDate['days'] = $days; 
                $this->filterDate['column'] = $column;
            
            }

            public function filterLimit( bool $visible = false ) { 
                
                $this->filterLimit['visible'] = $visible; 
            
            }

            public function filterSearch( bool $visible = false, array $fields = [] ) {

                $this->filterSearch['visible'] = $visible; 
                $this->filterSearch['fields'] = $fields; 
            
            }

            /**
             * Undocumented function
             *
             * @param [type] $column
             * @param array $array
             * @param [type] $input = select || checkbox || radio || tree
             * @param bool $search
             * @param [type] $columnType = null || multiple
             * @return void
             */
            public function addFilter( $label, $column, array $array, $input = 'select', bool $search = false, $columnType = null ) {

                array_push(
                    $this->filterCustom, [
                        'label' => $label,
                        'column' => $column,
                        'column_type' => $columnType,
                        'array' => $array,
                        'input' => $input,
                        'search' => $search
                    ]
                );

            }


        #

        
        private function createId( string $id ) { return $this->table.'__'.$id; }

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
        public function addColumn( $label, $column, bool $orderable = false, $class = '', $hiddenDevice = null, $width = null, $format = []) {

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
                'id' => $this->columnId,
                'name' => $column,
                'title' => $label,
                'orderable' => $orderable,
                'className' => $class,
                'width' => $width,
                'searchable' => false,
                'other' => $format
            ]);

            $this->columnId++;

        }

        public function text( $titleS = 'elemento', $titleP = 'elementi', $last = 'ultimi', $all = 'tutti', $article = 'gli', $full = 'pieno', $empty = 'vuoto', $thiss = 'questo' ) {

            $this->text['titleS'] = $titleS;
            $this->text['titleP'] = $titleP;
            $this->text['last'] = $last;
            $this->text['all'] = $all;
            $this->text['article'] = $article;
            $this->text['full'] = $full;
            $this->text['empty'] = $empty;
            $this->text['this'] = $thiss;

        }

        private function getFromUrl( $element ) {

            $array = [];

            if ($element == 'filter_date') {
                
                if (isset($_GET['date_from']) && !empty($_GET['date_from'])) { $array['date_from'] = $_GET['date_from']; }
                if (isset($_GET['date_to']) && !empty($_GET['date_to'])) { $array['date_to'] = $_GET['date_to']; }
                if (isset($_GET['month']) && !empty($_GET['month'])) { $array['month'] = $_GET['month']; }
                if (isset($_GET['year']) && !empty($_GET['year'])) { $array['year'] = $_GET['year']; }

            } else if ($element == 'filter_custom') {

                foreach ($this->filterCustom as $key => $options) {

                    $columnName = $options['column'];
                    $name = $this->createId($columnName);

                    if (isset($_GET[$name]) && !empty($_GET[$name])) { $array[$name] = $_GET[$name]; }
                    
                }

            }

            return $array;

        }

        private function createFilterDate() {

            $RETURN = '';

            if ($this->filterDate['visible']) {

                $FILTER = new FilterDate( $this->table, $this->mysqli, $this->filterDate['days'], $this->filterDate['column'], $this->getFromUrl('filter_custom'));

                $RETURN = $FILTER->filter;
                $this->titleValue = ucwords($this->text['titleP']).' '.$FILTER->title;
                $this->queryFilter .= empty($this->queryFilter) ? $FILTER->query : $this->queryFilter.'AND '.$FILTER->query;

            }

            return $RETURN;

        }

        private function createFilterCustom() {

            $FILTER_HTML = '';
            $FILTER_BUTTON_HTML = '';

            if (!empty($this->filterCustom)) {

                $FILTER = new FilterCustom( $this->table, $this->mysqli, $this->filterCustom, true, $this->getFromUrl('filter_date') );

                $FILTER_HTML = $FILTER->filter;
                $this->queryFilter .= empty($this->queryFilter) ? $FILTER->query : $this->queryFilter.'AND '.$FILTER->query;

                $class = ($this->filterSearch['visible']) ? 'pe-0' : 'me-auto';

                $FILTER_BUTTON_HTML .= '<div class="col-auto '.$class.'">';
                $FILTER_BUTTON_HTML .=  $FILTER->button;
                $FILTER_BUTTON_HTML .= '</div>';
                
            }

            $RETURN = (object) [];
            $RETURN->filter = $FILTER_HTML;
            $RETURN->button = $FILTER_BUTTON_HTML;

            return $RETURN;

        }

        private function createTitle() {

            $RETURN = '';

            if ($this->title) {

                $col = ($this->buttonAdd['visible']) ? 'col-8' : 'col-12';

                $title = empty($this->titleValue) ? 'Lista '.$this->text['titleP'] : $this->titleValue;

                $RETURN .= '<div class="'.$col.'"><h3>'.$title.'</h3>';

                if ($this->titleNResult) { $RETURN .= '<figcaption class="text-muted"> Risultati: <span id="'.$this->id['n_result'].'"></span> </figcaption>'; }

                $RETURN .= '</div>';

            } else if ($this->titleNResult) {

                $col = ($this->buttonAdd['visible']) ? 'col-8' : 'col-12';

                $RETURN .= '<div class="'.$col.'">';
                $RETURN .= '<figcaption class="text-muted"> Risultati: <span id="'.$this->id['n_result'].'"></span> </figcaption>';
                $RETURN .= '</div>';

            }

            return $RETURN;

        }

        private function rowHeader() {

            $FILTER_DATE_HTML = $this->createFilterDate(); # Da mettere sempre prima di creare il titolo
            $FILTER_CUSTOM = $this->createFilterCustom();

            $TITLE_HTML = $this->createTitle();

            $BUTTON_ADD_HTML = '';
            
            if ($this->buttonAdd['visible']) {

                $col = ($this->title) ? 'col-4' : 'col-12';
                $href = $this->buttonAdd['href'];
                $name = $this->buttonAdd['name'];
                $icon = $this->buttonAdd['icon'];

                $BUTTON_ADD_HTML .= '<div class="'.$col.'"><a href="'.$href.'" type="button" class="btn btn-dark btn-sm float-end href-redirect"> '.$icon.' '.$name.' </a></div>';

            }


            $BUTTON_CUSTOM_HTML = '';

            if (!empty($this->buttonCustom)) {

                $BUTTON_CUSTOM_HTML .= '<div class="col-12 d-flex gap-2 justify-content-end">';

                foreach ($this->buttonCustom as $key => $button) {

                    $isHTML = isset($button['is_html']) ? $button['is_html'] : false;
                    $value = isset($button['value']) ? $button['value'] : '';
                    $action = isset($button['action']) ? $button['action'] : '';
                    $color = isset($button['color']) ? $button['color'] : 'dark';

                    if ($isHTML) {
                        $BUTTON_CUSTOM_HTML .=  $value;
                    } else {
                        $BUTTON_CUSTOM_HTML .=  '<a '.$action.' type="button" class="btn btn-'.$color.' btn-sm">'.$value.'</a>';
                    }
                    
                }
                
                $BUTTON_CUSTOM_HTML .= '</div>';

            }


            $SEARCH_HTML = '';

            if ($this->filterSearch['visible']) {
                
                $SEARCH_HTML .= '<div class="col-4 me-auto">';
                $SEARCH_HTML .= '<div class="input-group input-group-sm">';
                $SEARCH_HTML .= '<span class="input-group-text user-select-none">Cerca: </span>';
                $SEARCH_HTML .= '<input type="text" class="form-control" id="'.$this->id['search_input'].'">';
                $SEARCH_HTML .= '</div>';
                $SEARCH_HTML .= '</div>';

            }


            $FILTER_LIMIT_HTML = '';

            if ($this->filterLimit['visible']) {

                $FILTER_LIMIT_HTML .= '<div class="col-3">';
                $FILTER_LIMIT_HTML .= '<div class="input-group input-group-sm">';
                $FILTER_LIMIT_HTML .= '<span class="input-group-text user-select-none">Mostra:</span>';
                $FILTER_LIMIT_HTML .= '<select class="form-select" id="'.$this->id['limit_input'].'">';
                $FILTER_LIMIT_HTML .= '<option value="10">10 elementi</option>';
                $FILTER_LIMIT_HTML .= '<option value="25">25 elementi</option>';
                $FILTER_LIMIT_HTML .= '<option value="50">50 elementi</option>';
                $FILTER_LIMIT_HTML .= '<option value="100">100 elementi</option>';
                $FILTER_LIMIT_HTML .= '</select>';
                $FILTER_LIMIT_HTML .= '</div>';
                $FILTER_LIMIT_HTML .= '</div>';

            }


            $BUTTON_DOWNLOAD_HTML = '';

            if ($this->buttonDownload['visible']) {

                $BUTTON_DOWNLOAD_HTML .= '<div class="col-auto ps-0">';
                $BUTTON_DOWNLOAD_HTML .= '<div class="btn-group float-end" role="group">';
                $BUTTON_DOWNLOAD_HTML .= '<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"> Esporta </button>';
                $BUTTON_DOWNLOAD_HTML .= '<div class="dropdown-menu dropdown-menu-right">';

                foreach ($this->buttonDownload['format'] as $format => $label) {
                    $BUTTON_DOWNLOAD_HTML .= '<button onclick="exportTable(\''.$this->id['table'].'\', \''.$format.'\' )" class="dropdown-item">'.$label.'</button>';
                }

                $BUTTON_DOWNLOAD_HTML .= '</div>';
                $BUTTON_DOWNLOAD_HTML .= '</div>';
                $BUTTON_DOWNLOAD_HTML .= '</div>';

            }

            $RETURN = '';
            $RETURN .= $TITLE_HTML;
            $RETURN .= $BUTTON_ADD_HTML;
            $RETURN .= $FILTER_DATE_HTML;
            $RETURN .= $BUTTON_CUSTOM_HTML;
            $RETURN .= $FILTER_CUSTOM->button;
            $RETURN .= $SEARCH_HTML;
            $RETURN .= $FILTER_LIMIT_HTML;
            $RETURN .= $BUTTON_DOWNLOAD_HTML;
            $RETURN .= $FILTER_CUSTOM->filter;

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

        private function script() {

            $JSON = [];
            $JSON['id'] = $this->id['table'];
            $JSON['url'] = $this->url;
            $JSON['fields'] = $this->columns;
            $JSON['custom'] = $this->endpointValues;

            if (empty($this->queryFilter)) {
                $orderColumn = $this->orderColumn;
                $orderDirection = $this->orderDirection;
            } else {
                $orderColumn = $this->orderColumnFilterActive;
                $orderDirection = $this->orderDirectionFilterActive;
            }

            $JSON['default'] = [
                'page' => isset($_GET[$this->id['page']]) ? $_GET[$this->id['page']] : $this->default['page'],
                'length' => isset($_GET[$this->id['length']]) ? $_GET[$this->id['length']] : $this->default['length'],
                'search' => isset($_GET[$this->id['search']]) ? $_GET[$this->id['search']] : $this->default['search'],
                'order' => isset($_GET[$this->id['order']]) ? $_GET[$this->id['order']] : $orderColumn,
                'order_direction' => isset($_GET[$this->id['order_dir']]) ? $_GET[$this->id['order_dir']] : $orderDirection,
                'link' => $this->link
            ];

            $JSON['config'] = [
                'table' => $this->table,
                'database' =>$this->database,
                'query' => base64_encode($this->query),
                'query_filter' => base64_encode($this->queryFilter),
                'query_custom' => base64_encode($this->queryCustom),
                'search_column' => base64_encode(json_encode($this->filterSearch['fields']))
            ];

            $JSON['text'] = $this->text;

            $SCRIPT = '<script>';
            $SCRIPT .= "window.addEventListener('loaded', (event) => {";
            $SCRIPT .= "createDataTables('".$this->id['table']."', '".$this->endpoint."', ".json_encode($JSON).")";
            $SCRIPT .= "})";
            $SCRIPT .= '</script>';

            return $SCRIPT;

        }

        public function generate( $card = true ) {

            if (empty($this->queryFilter) && empty($this->queryCustom)) {
                $this->query = '';
            } else if (!empty($this->queryFilter) && !empty($this->queryCustom)) {
                $this->query = $this->queryFilter.'AND '.$this->queryCustom;
            } else if (!empty($this->queryFilter)) {
                $this->query = $this->queryFilter;
            } else if (!empty($this->queryCustom)) {
                $this->query = $this->queryCustom;
            }

            $CONTENT = $this->rowHeader();
            $CONTENT .= $this->rowTable();
            $CONTENT .= $this->script();

            if ($card) {
                return '<wi-card class="col-12">'.$CONTENT.'</wi-card>';
            } else {
                return $CONTENT;
            }

        }

    }