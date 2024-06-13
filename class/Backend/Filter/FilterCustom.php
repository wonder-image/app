<?php

    namespace Wonder\Backend\Filter;

    use Wonder\Sql\Query;

    /**
     * 
     * Funzioni esterne utilizzate:
     *  - select ( input )
     *  - check ( input )
     *  - checkTree ( input )
     * 
     */
    class FilterCustom {

        # Connessione alla tabella
            public $table, $mysqli, $SQL;

        # Valori compilati da __construct
            public $query;  # Query
            public $filter; # Filtro this->filter
            public $button; # Bottone this->filter

        # Opzioni
            public $filterColumn, $dropdown, $customGet;

        function __construct( $table, $mysqli, array $filterColumn, bool $dropdown, array $customGet = []) {
        
            $this->table = $table;
            $this->mysqli = $mysqli;

            $this->SQL = new Query( $this->mysqli );

            $this->filterColumn = $filterColumn;
            $this->dropdown = $dropdown;
            $this->customGet = $customGet;

            $this->generate();

        }


        private function generate() {

            $this->filter = '';
            $this->button = '';

            $dropdownId = $this->table.'__filter_container';
            $FILTER_USED = 0;

            $this->filter .= '<div id="'.$dropdownId.'" class="col-12 collapse border-top border-bottom">';
            $this->filter .= '<form action="" method="get" onsubmit="loadingSpinner()" class="my-3">';
            $this->filter .= '<div class="col-12">';
            $this->filter .= '<div class="container p-0" style="max-width: 100%;">';
            $this->filter .= '<div class="row g-3">';

            # Valori da tenere in considerazione
                foreach ($this->customGet as $key => $value) { 
                    $this->filter .= '<input type="hidden" name="'.$key.'" value="'.$value.'">'; 
                }

            #

            foreach ($this->filterColumn as $key => $options) {

                $this->filter .= '<div class="col-3">';

                $columnName = $options['column'];
                $name = $this->table.'__'.$columnName;
                $inputType = $options['input'];
                $columnType = $options['column_type'];
                $value = isset($_GET[$name]) ? $_GET[$name] : null;

                if ($inputType == 'select') {
                    $this->filter .= select($options['label'], $name, $options['array'], 'old', null, $value);
                } else if ($inputType == 'checkbox') {
                    $this->filter .= check($options['label'], $name, $options['array'], null, 'checkbox', $options['search'], $value);
                } else if ($inputType == 'radio') {
                    $this->filter .= check($options['label'], $name, $options['array'], null, 'radio', $options['search'], $value);
                } else if ($inputType == 'tree') {
                    $this->filter .= checkTree($options['label'], $name, $options['array'], null, 'checkbox', true, $value);
                }

                $this->filter .= '</div>';

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

                                $query = "`$columnName` LIKE '%\"$value\"%' ";

                            } else {

                                $query = "`$columnName` = '$value' ";

                            }

                        }

                        $this->query .= empty($this->query) ? $query : 'AND '.$query;
                        $FILTER_USED++;

                    }

                #

            }


            $this->filter .= '<div class="col-3">';
            $this->filter .= '<button type="submit" class="btn btn-dark btn-sm"> <i class="bi bi-search"></i> Applica filtri </button>';
            $this->filter .= '</div>';

            $this->filter .= '</div>';
            $this->filter .= '</div>';
            $this->filter .= '</div>';
            $this->filter .= '</form>';
            $this->filter .= '</div>';

            $this->button .= '<button type="button" class="position-relative btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#'.$dropdownId.'" aria-expanded="false">';
            $this->button .= '<i class="bi bi-filter"></i> Filtri';
            $this->button .= ($FILTER_USED > 0) ? '<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary" style="--bs-badge-font-size: 0.7em;">'.$FILTER_USED.' <span class="visually-hidden">unread messages</span></span>' : '';
            $this->button .= '</button>';
            
        }

    }