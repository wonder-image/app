<?php

    namespace Wonder\Backend\Filter;

    use Wonder\Sql\Query;
    use Wonder\Plugin\Custom\Translator\TranslatorDate;

    use DateTime;

    class FilterDate {

        # Connessione alla tabella
            public $table, $mysqli, $SQL;

        # Titolo
            public $title;

        # Query
            public $query;

        # Filtro HTML
            public $filter;
            
        # Opzioni
            public $days, $column, $customGet;

        function __construct( $table, $mysqli, int $days = 30, string $column = 'creation', array $customGet = []) {
            
            $this->table = $table;
            $this->mysqli = $mysqli;

            $this->SQL = new Query( $this->mysqli );

            $this->days = $days;
            $this->column = $column;
            $this->customGet = $customGet;

            $this->generate();

        }

        private function generate() {

            # Valori da tenere in considerazione 
                $QUERY_URL = '';
                $QUERY_INPUT = '';

                foreach ($this->customGet as $key => $value) {
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

            # Creo i bottoni mesi
                
                # Per creare il primo bottone cerco il primo valore inserito
                    $firstRow = $this->SQL->Select($this->table, null, 1, $this->column, 'ASC');

                    if ($firstRow->exists) {

                        # Se esiste una linea uso la sua data
                        $tableCreation = $firstRow->row[$this->column];
                        $firstDate = new DateTime($tableCreation);
                        
                    } else {

                        # Se non esiste nessuna linea uso la data corrente
                        $firstDate = new DateTime('now');

                    }

                #

                $firstDate = $firstDate->modify('-1 month');
                $lastDate = new DateTime('now');

                $im = 1;

                $BUTTONS_MONTH = "";
                $OTHER_BUTTONS_MONTH = "";

                while ($lastDate >= $firstDate) {

                    $month = $lastDate->format('F');
                    $year = $lastDate->format('Y');

                    $translator = new TranslatorDate();
                    $mese = $translator->Month("01-$month-$year");

                    if (isset($_GET[$monthName]) && isset($_GET[$yearName]) && $month == $_GET[$monthName] && $year == $_GET[$yearName]) {

                        $outline = "";
                        $active = "active";

                        $from = $lastDate->format('01/m/Y');
                        $to = $lastDate->format('t/m/Y');

                        $this->title = "di $mese ".$year;

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
            
            #

                if (empty($from) && empty($to)) {

                    $DAYS = $this->days;

                    $date = new DateTime('now');

                    $to = $date->format('d/m/Y');
                    $from = $date->modify('-'.$DAYS.' days')->format('d/m/Y');
                    
                    if ($DAYS == 0) {
                        $this->title = 'di oggi';
                    } else {
                        $this->title = 'ultimi '.$DAYS.' giorni';
                    }

                }
                    
                $this->title = empty($this->title) ? "dal $from al $to" : $this->title;

                $this->filter .= '<div class="col-5">';
                $this->filter .= '<form action="" method="get" onsubmit="loadingSpinner()">';
                $this->filter .= '<div class="input-group input-group-sm input-daterange" data-wi-date-range="true">';
                $this->filter .= '<span class="input-group-text">Da</span>';
                $this->filter .= '<input type="text" class="form-control bg-transparent" name="'.$fromName.'" value="'.$from.'" readonly>';
                $this->filter .= '<span class="input-group-text">A</span>';
                $this->filter .= '<input type="text" class="form-control bg-transparent" name="'.$toName.'" value="'.$to.'" readonly>';
                $this->filter .= '<button type="submit" class="btn btn-dark"> <i class="bi bi-search"></i> Cerca </button>';
                $this->filter .= '</div>';
                $this->filter .= '</form>';
                $this->filter .= '</div>';
                $this->filter .= '<div class="col-12">';
                $this->filter .= '<span>Filtra per mese:</span>';
                $this->filter .= '<div class="container mt-1" style="max-width: 100%;">';
                $this->filter .= '<div class="row row-cols-auto gap-2">';
                $this->filter .= $BUTTONS_MONTH;
                $this->filter .= '</div>';
                $this->filter .= '</div>';
                $this->filter .= '</div>';

            # Creo la query
                
                list($day,$month,$year) = explode("/", $from);
                $fromSQL = "$year-$month-$day";
                list($day,$month,$year) = explode("/", $to);
                $toSQL = "$year-$month-$day";

                $this->query = "`".$this->column."` BETWEEN '".$fromSQL." 00:00:00' AND '".$toSQL." 23:59:59' ";

            #
            
        }


    }