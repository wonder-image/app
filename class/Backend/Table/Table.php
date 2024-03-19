<?php

    namespace Wonder\Backend\Table;
    
    class Table {

        public $title = false;
        public $titleName = "";
        public $titleResult = false;

        public $buttonAdd = false;
        public $buttonAddName = "";

        public $buttonCustom = [];

        public $searchFields = [];

        public $filter = "";
        public $filterCustom = [];

        public $table = "";
        public $database = "main";

        private $query = "";

        private $userArea = [];
        private $userAuthority = [];


        function __construct($table) {

            $this->table = $table;

        }

        public function title( bool $title, bool $result = true ) { 

            $this->title = $title;
            $this->titleResult = $result; 

        }

        public function buttonAdd( string $name ) { 

            $this->buttonAdd = empty($name) ? false : true;
            $this->buttonAddName = $name;

        }

        public function buttonCustom( array $button ) { $this->buttonCustom = $button; }
        
        public function search( array $fields ) { $this->searchFields = $fields; }

        public function queryCustom( string $query ) { $this->query .= $query; }

        public function filter( string $filter ) { $this->filter = $filter; }
        public function filterCustom( array $filter ) { $this->filterCustom = $filter; }


        public function filterUser( array|string $authority, array|string $area ) {

            $this->query .= empty($this->query) ? $this->query.' AND ' : '';

            if (!empty($authority)) {

                if (is_array($authority)) {
    
                    $this->query .= '(';

                    foreach ($authority as $k => $auth) {

                        $this->query .= "`authority` LIKE '%$auth%' OR "; 
                        array_push($this->userAuthority, $auth);
                    
                    }

                    $this->query = substr($this->query, 0, -4).')';
    
                } else {
    
                    $this->query .= "`authority` LIKE '%$authority%'";
                    array_push($this->userAuthority, $authority);
    
                }

            }
    
            if (!empty($authority) && !empty($area)) { $this->query .= " AND "; }
    
            if (!empty($area)) {

                if (is_array($area)) {
    
                    $this->query .= '(';

                    foreach ($area as $k => $v) { 
                        
                        $this->query .= "`area` LIKE '%$v%' OR "; 
                        array_push($this->userArea, $v);
                    
                    }

                    $this->query = substr($this->query, 0, -4).')';
    
                } else {
    
                    $this->query .= "`area` LIKE '%$area%'";
                    array_push($this->userArea, $area);
                    
                }
                
            }

        }

        public function text($titleS = 'riga', $titleP = 'righe', $last = 'ultime', $all = 'tutte', $article = 'le', $full = 'piena', $empty = 'vuota', $this = 'questa') {

        }

        private function rowHeader() {

            $RETURN = "";

            if ($this->title) {

                $col = ($this->buttonAdd) ? 'col-8' : 'col-12';

                $RETURN .= '<div class="'.$col.'"><h3>'.$this->title.'</h3>';

                if ($this->titleResult) { $RETURN .= '<figcaption class="text-muted"> Risultati: <span id="wi-table-result"></span> </figcaption>'; }

                $RETURN .= '</div>';

            }
            
            if ($this->buttonAdd) {

                $col = ($this->buttonAdd) ? 'col-4' : 'col-12';

                $RETURN .= '<div class="'.$col.'"><a href="'.$PATH->backend.'/'.$NAME->folder.'/?redirect='.$PAGE->uriBase64.'" type="button" class="btn btn-dark btn-sm float-end"> <i class="bi bi-plus-lg"></i> Aggiungi '.$this->buttonAdd.' </a></div>';

            }

            return $RETURN;

        }

        private function rowFilter() {



        }

        private function rowTable() {



        }

        public function generate() {

            return $this->rowHeader().$this->rowFilter().$this->rowTable();
            
        }


    }