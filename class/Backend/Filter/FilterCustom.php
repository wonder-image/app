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

        function __construct( $table, $mysqli, array $filterColumn, bool $dropdown = true, array $customGet = []) {
        
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
                $this->filter .= self::hiddenInputs($this->customGet);

            #

            foreach ($this->filterColumn as $key => $options) {

                $this->filter .= '<div class="col-3">';

                $columnName = $options['column'];
                $name = $this->table.'__'.$columnName;
                $inputType = $options['input'];
                $value = $_GET[$name] ?? ($options['value'] ?? null);

                switch ($inputType) {
                    case 'select':
                        $this->filter .= select($options['label'], $name, $options['array'], 'old', null, $value);
                        break;
                    case 'checkbox':
                        $this->filter .= check($options['label'], $name, $options['array'], null, 'checkbox', $options['search'], $value);
                        break;
                    case 'radio':
                        $this->filter .= check($options['label'], $name, $options['array'], null, 'radio', $options['search'], $value);
                        break;
                    case 'tree':
                        $this->filter .= checkTree($options['label'], $name, $options['array'], null, 'checkbox', true, $value);
                        break;
                }

                $this->filter .= '</div>';

                # Creo la query
                    $query = self::condition($options, $value);

                    if ($query !== '') {
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

        /**
         * Condizione SQL di un filtro per il valore arrivato da GET (o dal
         * default). Vuota se non c'è niente da filtrare: nessun valore, solo
         * l'input nascosto delle checkbox, un array su un filtro a scelta
         * singola. Valori escapati; con `column_type` multiple le LIKE stanno
         * fra parentesi.
         */
        public static function condition( array $options, mixed $value ): string
        {
            $input = (string) ($options['input'] ?? 'select');
            $values = self::values($value, in_array($input, ['checkbox', 'tree'], true));

            if ($values === []) {
                return '';
            }

            $where = $options['where'] ?? null;

            if ($where instanceof \Closure) {
                $values = array_values(array_intersect($values, self::optionKeys((array) ($options['array'] ?? []))));

                if ($values === []) {
                    return '';
                }

                $sql = trim((string) $where($values));

                return $sql === '' ? '' : '('.$sql.') ';
            }

            $column = Query::escapeIdentifier((string) ($options['column'] ?? ''));

            if (($options['column_type'] ?? null) === 'multiple') {
                $likes = array_map(
                    static fn (string $v) => $column." LIKE '%\"".addcslashes(addslashes($v), '%_')."\"%'",
                    $values
                );

                return '('.implode(' OR ', $likes).') ';
            }

            if (is_array($value)) {
                return $column.' IN ('.implode(', ', array_map(static fn (string $v) => "'".addslashes($v)."'", $values)).') ';
            }

            return $column." = '".addslashes($values[0])."' ";
        }

        /**
         * Valori utili come stringhe, senza doppioni. Un array vale solo per
         * checkbox e tree; restano fuori booleani, array annidati e stringhe
         * vuote (l'input nascosto delle checkbox).
         *
         * @return array<int, string>
         */
        private static function values( mixed $value, bool $list ): array
        {
            $values = is_array($value) ? ($list ? $value : []) : [$value];
            $out = [];

            foreach ($values as $v) {
                if (is_bool($v) || !is_scalar($v) || (string) $v === '') {
                    continue;
                }

                $out[] = (string) $v;
            }

            return array_values(array_unique($out));
        }

        /**
         * Chiavi delle opzioni come stringhe, figli compresi (`child` degli
         * alberi di checkTree).
         *
         * @return array<int, string>
         */
        private static function optionKeys( array $options ): array
        {
            $keys = [];

            foreach ($options as $key => $option) {
                $keys[] = (string) $key;

                if (is_array($option) && is_array($option['child'] ?? null)) {
                    $keys = array_merge($keys, self::optionKeys($option['child']));
                }
            }

            return $keys;
        }

        /**
         * Input nascosti per i parametri GET da conservare, con nome e valore
         * escapati. Gli array restano fuori.
         *
         * @param array<string, mixed> $values
         */
        public static function hiddenInputs( array $values ): string
        {
            $html = '';

            foreach ($values as $name => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }

                $html .= '<input type="hidden" name="'.htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8').'" value="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'">';
            }

            return $html;
        }

    }