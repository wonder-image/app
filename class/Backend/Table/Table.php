<?php

    namespace Wonder\Backend\Table;
    
    use Wonder\Sql\Query;

    /**
     * 
     * Funzioni esterne utilizzate:
     *  - translateDate
     *  - sqlTableInfo
     *  - select ( input )
     *  - check ( input )
     *  - checkTree ( input )
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


        function __construct( $table, $mysqli ) {

            $this->table = $table;
            $this->mysqli = $mysqli;

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
        public function queryOrder( string $column, string $direction, string $columnWhenFilterIsActive = null, string $directionWhenFilterIsActive = null ) { 

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
        public function addColumn( $label, $column, bool $orderable = false, $class = '', $hiddenDevice = '', $width = 'auto', $other = []) {

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
                'other' => $other
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

               # Valori da tenere in considerazione 
                    $QUERY_URL = '';
                    $QUERY_INPUT = '';

                    foreach ($this->getFromUrl('filter_custom') as $key => $value) {
                        $QUERY_URL .= '&'.$key.'='.$value.'';
                        $QUERY_INPUT .= '<input type="hidden" name="'.$key.'" value="'.$value.'">'; 
                    }

                #

                $fromName = 'date_from';
                $toName = 'date_to';

                $monthName = 'month';
                $yearName = 'year';

                $from = isset($_GET[$fromName]) ? $_GET[$fromName] : '';
                $to = isset($_GET[$toName]) ? $_GET[$toName] : '';

                $tableCreation = $this->SQL->Select($this->table, null, 1, $this->filterDate['column'], 'ASC')->row[$this->filterDate['column']];

                # Creo i bottoni mesi
                    
                    $firstDate = new DateTime($tableCreation);
                    $firstDate = $firstDate->modify('-1 month');
                    $lastDate = new DateTime('now');

                    $im = 1;

                    $BUTTONS_MONTH = "";
                    $OTHER_BUTTONS_MONTH = "";

                    while ($lastDate >= $firstDate) {

                        $month = $lastDate->format('F');
                        $year = $lastDate->format('Y');

                        $mese = translateDate("01-$month-$year", 'month');

                        if (isset($_GET[$monthName]) && isset($_GET[$yearName]) && $month == $_GET[$monthName] && $year == $_GET[$yearName]) {

                            $outline = "";
                            $active = "active";

                            $from = $lastDate->format('01/m/Y');
                            $to = $lastDate->format('t/m/Y');

                            $this->titleValue = ucwords($this->text['titleP'])." di $mese ".$year;

                        } else {

                            $outline = "-outline";
                            $active = "";

                        }

                        if ($im < 5) {
                            $BUTTONS_MONTH .= '<a href="?'.$monthName.'='.$month.'&'.$yearName.'='.$year.''.$QUERY_URL.'" class="btn btn'.$outline.'-dark btn-sm col" tabindex="-1" role="button"> '.$mese.' '.$year.' </a>';
                        } else {
                            $OTHER_BUTTONS_MONTH .= '<a href="?'.$monthName.'='.$month.'&'.$yearName.'='.$year.''.$QUERY_URL.'" class="dropdown-item '.$active.'"> '.$mese.' '.$year.' </a>';
                        }
                        
                        $im++;

                        $lastDate = $lastDate->modify('-1 month');

                    }

                    if (!empty($OTHER_BUTTONS_MONTH)) {
                            
                        $BUTTONS_MONTH .= '<div class="dropdown col p-0">';
                        $BUTTONS_MONTH .= '<button type="button" class="btn btn-outline-dark btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"> Altro </button>';
                        $BUTTONS_MONTH .= '<div class="dropdown-menu">';
                        $BUTTONS_MONTH .= $OTHER_BUTTONS_MONTH;
                        $BUTTONS_MONTH .= '</div>';
                        $BUTTONS_MONTH .= '</div>';

                    }

                    if (empty($from) && empty($to)) {

                        $DAYS = $this->filterDate['days'];

                        $date = new DateTime('now');

                        $from = $date->modify('-'.$DAYS.' month')->format('d/m/Y');
                        $to = $date->format('d/m/Y');

                        if ($DAYS == 0) {
                            $this->titleValue = ucwords($this->text['titleP'])." di oggi";
                        } else {
                            $this->titleValue = ucwords($this->text['titleP'])." ultimi $DAYS giorni";
                        }

                    }
                        
                    $this->titleValue = empty($this->titleValue) ? ucwords($this->text['titleP'])." dal $from al $to" : $this->titleValue;

                    $RETURN .= '<div class="col-5">';
                    $RETURN .= '<form action="" method="get" onsubmit="loadingSpinner()">';
                    $RETURN .= '<div class="input-group input-group-sm input-daterange" data-wi-date-range="true">';
                    $RETURN .= '<span class="input-group-text">Da</span>';
                    $RETURN .= '<input type="text" class="form-control bg-transparent" name="'.$fromName.'" value="'.$from.'" readonly>';
                    $RETURN .= '<span class="input-group-text">A</span>';
                    $RETURN .= '<input type="text" class="form-control bg-transparent" name="'.$toName.'" value="'.$to.'" readonly>';
                    $RETURN .= '<button type="submit" class="btn btn-dark"> <i class="bi bi-search"></i> Cerca </button>';
                    $RETURN .= '</div>';
                    $RETURN .= '</form>';
                    $RETURN .= '</div>';
                    $RETURN .= '<div class="col-12">';
                    $RETURN .= '<span>Filtra per mese:</span>';
                    $RETURN .= '<div class="container mt-1" style="max-width: 100%;">';
                    $RETURN .= '<div class="row row-cols-auto gap-2">';
                    $RETURN .= $BUTTONS_MONTH;
                    $RETURN .= '</div>';
                    $RETURN .= '</div>';
                    $RETURN .= '</div>';

                # Creo la query
                    
                    list($day,$month,$year) = explode("/", $from);
                    $fromSQL = "$year-$month-$day";
                    list($day,$month,$year) = explode("/", $to);
                    $toSQL = "$year-$month-$day";

                    $query = "`".$this->filterDate['column']."` BETWEEN '".$fromSQL." 00:00:00' AND '".$toSQL." 23:59:59' ";
                    $this->queryFilter .= empty($this->queryFilter) ? $query : 'AND '.$query;

            }

            return $RETURN;

        }

        private function createFilterCustom() {

            $FILTER_HTML = '';
            $FILTER_BUTTON_HTML = '';
            $FILTER_USED = 0;

            if (!empty($this->filterCustom)) {

                $FILTER_HTML .= '<div id="'.$this->id['filter_container'].'" class="col-12 collapse border-top border-bottom">';
                $FILTER_HTML .= '<form action="" method="get" onsubmit="loadingSpinner()" class="my-3">';
                $FILTER_HTML .= '<div class="col-12">';
                $FILTER_HTML .= '<div class="container p-0" style="max-width: 100%;">';
                $FILTER_HTML .= '<div class="row g-3">';

               # Valori da tenere in considerazione
                    foreach ($this->getFromUrl('filter_date') as $key => $value) { 
                        $FILTER_HTML .= '<input type="hidden" name="'.$key.'" value="'.$value.'">'; 
                    }

                #

                foreach ($this->filterCustom as $key => $options) {

                    $FILTER_HTML .= '<div class="col-3">';

                    $columnName = $options['column'];
                    $name = $this->createId($columnName);
                    $inputType = $options['input'];
                    $columnType = $options['column_type'];
                    $value = isset($_GET[$name]) ? $_GET[$name] : null;

                    if ($inputType == 'select') {
                        $FILTER_HTML .= select($options['label'], $name, $options['array'], 'old', null, $value);
                    } else if ($inputType == 'checkbox') {
                        $FILTER_HTML .= check($options['label'], $name, $options['array'], null, 'checkbox', $options['search'], $value);
                    } else if ($inputType == 'radio') {
                        $FILTER_HTML .= check($options['label'], $name, $options['array'], null, 'radio', $options['search'], $value);
                    } else if ($inputType == 'tree') {
                        $FILTER_HTML .= checkTree($options['label'], $name, $options['array'], null, 'checkbox', true, $value);
                    }

                    $FILTER_HTML .= '</div>';

                    # Creo la query 
                        if ($value != null) {
                            
                            if (($inputType == "checkbox" || $inputType == "tree") && is_array($value)) {

                                unset($value[0]); # Elimino il primo valore perchè negli input checkbox il primo valore è un input:hidden

                                if ($columnType == "multiple") {

                                    $query = "";

                                    foreach ($value as $key => $v) { $query .= "`$columnName` LIKE '%\"$v\"%' OR "; }

                                    $query = substr($query, 0, -4);

                                } else {

                                    $query = "`$columnName` IN (";

                                    foreach ($value as $key => $v) { $query .= "'$v', "; }
                                    
                                    $query = substr($query, 0, -2);
                                    $query .= ") ";

                                }
                                

                            } else {

                                if ($columnType == "multiple") {

                                    $query = "`$columnName` LIKE '%\"$value\"%'";

                                } else {

                                    $query = "`$columnName` = '$value'";

                                }

                            }

                            $this->queryFilter .= empty($this->queryFilter) ? $query : 'AND '.$query;
                            $FILTER_USED++;

                        }

                    #

                }

                $FILTER_HTML .= '<div class="col-3">';
                $FILTER_HTML .= '<button type="submit" class="btn btn-dark btn-sm"> <i class="bi bi-search"></i> Applica filtri </button>';
                $FILTER_HTML .= '</div>';

                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</div>';
                $FILTER_HTML .= '</form>';
                $FILTER_HTML .= '</div>';

                $class = ($this->filterSearch['visible']) ? 'pe-0' : 'me-auto';

                $FILTER_BUTTON_HTML .= '<div class="col-auto '.$class.'">';
                $FILTER_BUTTON_HTML .= '<button type="button" class="position-relative btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#'.$this->id['filter_container'].'" aria-expanded="false">';
                $FILTER_BUTTON_HTML .= '<i class="bi bi-filter"></i> Filtri';
                $FILTER_BUTTON_HTML .= ($FILTER_USED > 0) ? '<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary" style="--bs-badge-font-size: 0.7em;">'.$FILTER_USED.' <span class="visually-hidden">unread messages</span></span>' : '';
                $FILTER_BUTTON_HTML .= '</button>';
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