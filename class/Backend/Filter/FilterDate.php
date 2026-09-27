<?php

    namespace Wonder\Backend\Filter;

    use Wonder\Sql\Query;
    use Wonder\Support\Prettify\Date;

    use DateTime;
    use DateTimeImmutable;

    class FilterDate {

        # Anni ammessi: l'intervallo di DATETIME in MySQL
            public const MIN_YEAR = 1000;
            public const MAX_YEAR = 9999;

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

            $fromName = 'date_from';
            $toName = 'date_to';

            $monthName = 'month';
            $yearName = 'year';

            # Valori del GET validati: quelli non validi valgono come assenti
                $from = self::parseDate($_GET[$fromName] ?? null)?->format('d/m/Y') ?? '';
                $to = self::parseDate($_GET[$toName] ?? null)?->format('d/m/Y') ?? '';

                $selectedMonth = self::parseInteger($_GET[$monthName] ?? null, 1, 12);
                $selectedYear = self::parseInteger($_GET[$yearName] ?? null, self::MIN_YEAR, self::MAX_YEAR);

            #

            # Creo i bottoni mesi

                $firstDate = $this->firstDate()->modify('-1 month');
                $lastDate = new DateTime('now');

                $im = 1;

                $BUTTONS_MONTH = "";
                $OTHER_BUTTONS_MONTH = "";

                while ($lastDate >= $firstDate) {

                    $month = (int) $lastDate->format('n');
                    $year = (int) $lastDate->format('Y');

                    $mese = Date::month($lastDate->format('Y-m-01'));

                    # I filtri personalizzati restano nel link del mese
                    $href = htmlspecialchars('?'.http_build_query([ $monthName => $month, $yearName => $year ] + $this->customGet, '', '&'), ENT_QUOTES, 'UTF-8');

                    if ($month === $selectedMonth && $year === $selectedYear) {

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
                        $BUTTONS_MONTH .= '<a href="'.$href.'" class="btn btn'.$outline.'-dark btn-sm col" tabindex="-1" role="button"> '.$mese.' '.$year.' </a>';
                    } else {
                        $OTHER_BUTTONS_MONTH .= '<a href="'.$href.'" class="dropdown-item '.$active.'"> '.$mese.' '.$year.' </a>';
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
                    
                if (empty($this->title)) {
                    if ($from !== '' && $to !== '') {
                        $this->title = "dal $from al $to";
                    } else if ($from !== '') {
                        $this->title = "dal $from";
                    } else {
                        $this->title = "fino al $to";
                    }
                }

                $this->filter .= '<div class="col-5">';
                $this->filter .= '<form action="" method="get" onsubmit="loadingSpinner()">';
                $this->filter .= '<div class="input-group input-group-sm input-daterange" data-wi-date-range="true">';
                $this->filter .= '<span class="input-group-text">Da</span>';
                $this->filter .= '<input type="text" class="form-control bg-transparent" name="'.$fromName.'" value="'.htmlspecialchars($from, ENT_QUOTES, 'UTF-8').'" readonly>';
                $this->filter .= '<span class="input-group-text">A</span>';
                $this->filter .= '<input type="text" class="form-control bg-transparent" name="'.$toName.'" value="'.htmlspecialchars($to, ENT_QUOTES, 'UTF-8').'" readonly>';
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

                $this->query = self::buildCondition($this->column, $from, $to);

            #

        }

        # Data del primo record: da qui parte il primo bottone mese
        protected function firstDate(): DateTime {

            $firstRow = $this->SQL->Select($this->table, null, 1, $this->column, 'ASC');

            if ($firstRow->exists) {

                # Se esiste una linea uso la sua data
                return new DateTime($firstRow->row[$this->column]);

            }

            # Se non esiste nessuna linea uso la data corrente
            return new DateTime('now');

        }

        # Condizione SQL del filtro: entrano solo date gg/mm/aaaa valide e un estremo
        # non valido non genera condizione. Table::buildConfig() firma questa stringa
        # con ConfigCodec, ma la firma non la rende sicura: va costruita sicura qui.
        public static function buildCondition(string $column, mixed $from, mixed $to): string {

            $from = self::parseDate($from);
            $to = self::parseDate($to);

            $column = Query::escapeIdentifier($column);

            if ($from !== null && $to !== null) {
                return $column." BETWEEN '".$from->format('Y-m-d')." 00:00:00' AND '".$to->format('Y-m-d')." 23:59:59' ";
            } else if ($from !== null) {
                return $column." >= '".$from->format('Y-m-d')." 00:00:00' ";
            } else if ($to !== null) {
                return $column." <= '".$to->format('Y-m-d')." 23:59:59' ";
            }

            return '';

        }

        # Validazione dei valori del GET, usata anche da filterDate() legacy
        # (app/function/backend/filter.php): null se il valore va scartato
        public static function parseDate(mixed $value): ?DateTimeImmutable {

            # Forma esatta prima del parsing: createFromFormat accetta 1/2/2026, salta
            # i caratteri non numerici in testa e lancia ValueError sui byte NUL
            if (!is_string($value) || !preg_match('#\A\d{2}/\d{2}/\d{4}\z#', $value)) {
                return null;
            }

            $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            # Il valore deve tornare identico: il 31/02 non diventa il 3 marzo
            $year = (int) $date->format('Y');

            if ($date->format('d/m/Y') !== $value || $year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                return null;
            }

            return $date;

        }

        public static function parseInteger(mixed $value, int $min, int $max): ?int {

            if (!is_string($value) || !preg_match('/\A\d{1,4}\z/', $value)) {
                return null;
            }

            $number = (int) $value;

            return ($number >= $min && $number <= $max) ? $number : null;

        }

    }
