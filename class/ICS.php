<?php

    namespace Wonder;

    /**
     * 
     * Per verificare il corretto funzionamento dell'esportazione [https://icalendar.org/validator.html]
     * 
     */
    
    class ICS {

        public $projectCreator = "Wonder Image";
        public $projectName = "Wonder Image Calendar";
        public $projectVersion = "1.0";
        public $projectLang = "IT";

        public $version = "2.0";
        public $name = "Calendario";
        public $scale = "GREGORIAN";
        public $timezone = "Europe/Rome";
        public $method = "PUBLISH";

        private $file;
        private $events = [];

        public function __construct($calendarName) { $this->name = $calendarName; }

        public function setProject($creator, $name, $version, $lang) {

            $this->projectCreator = $creator;
            $this->projectName = $name;
            $this->projectVersion = $version;
            $this->projectLang = $lang;

        }

        public function setVersion($version) { $this->version = $version; }
        public function setScale($scale) { $this->scale = $scale; }
        public function setTimezone($timezone) { $this->timezone = $timezone; }
        public function setMethod($method) { $this->method = $method; }
        public function createDate($date) { return date("Ymd", strtotime($date))."T".date("His", strtotime($date)); }

        private function code($lenght) {

            $characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $lenght; $i++) { $code .= $characters[rand(0, strlen($characters) - 1)]; }

            return $code.'_';

        }

        public function newAlarm($description, $trigger = '30M', $repeat = 1) {

            $alarm = [];
            $alarm[$this->code(4).'BEGIN'] = 'VALARM';
            $alarm[$this->code(4).'TRIGGER'] = '-PT'.$trigger;
            $alarm[$this->code(4).'REPEAT'] = $repeat;
            $alarm[$this->code(4).'ACTION'] = 'DISPLAY';
            $alarm[$this->code(4).'DESCRIPTION'] = $description;
            $alarm[$this->code(4).'END'] = 'VALARM';

            return $alarm;

        }

        public function newEvent($id, $title, $start, $end, $stamp, $status = 'CONFIRMED', $description = '', $position = '', $organizer = '', $frequency = '', $url = '', $conference = '', $alarm = null) {

            $event = [];
            $event[$this->code(4).'UID'] = $id; # Id
            $event[$this->code(4).'SUMMARY'] = $title; # Titolo

            if (substr($start, 8) == 'T000000' && substr($end, 8) == 'T000000') {
                $start = substr($start, 0, -7);
                $end = substr($end, 0, -7);
            }

            $event[$this->code(4).'DTSTART'] = $start; # Inizio evento
            $event[$this->code(4).'DTEND'] = $end; # Fine evento

            $event[$this->code(4).'DTSTAMP'] = $stamp; # Creazione
            $event[$this->code(4).'DESCRIPTION'] = $description; # Descrizione
            $event[$this->code(4).'LOCATION'] = $position; # Posizione
            $event[$this->code(4).'URL'] = $url; # Url
            $event[$this->code(4).'CONFERENCE'] = $conference; # Videochiamata
            $event[$this->code(4).'RRULE'] = $frequency; # Frequenza
            $event[$this->code(4).'ORGANIZER'] = $organizer; # Organizzatore
            $event[$this->code(4).'STATUS'] = strtoupper($status); # Stato TENTATIVE || CONFIRMED || CANCELLED || NEEDS-ACTION || IN-PROCESS || COMPLETED
            
            $event[$this->code(4).'SEQUENCE'] = "3"; 

            if ($alarm != null && is_array($alarm)) { 
                array_push($this->events, array_merge($event, $alarm)); 
            } else {
                array_push($this->events, $event);
            }

            # Allegato
            // $this->events .= "ATTACH:FMTTYPE=application/postscript:ftp://example.com/pub/reports/r-960812.ps\r\n";

            # ND
            // $this->file .= "ACTION:DISPLAY\r\n";

        }
        
        public function export($dir = null) {

            $LINE_BREAK = ($dir === null) ? "<br>" : "\r\n";

            $this->file = "BEGIN:VCALENDAR$LINE_BREAK";
            $this->file .= "PRODID:-//$this->projectCreator//$this->projectName $this->projectVersion//$this->projectLang$LINE_BREAK";
            $this->file .= "VERSION:$this->version$LINE_BREAK";
            $this->file .= "NAME:$this->name$LINE_BREAK";
            $this->file .= "X-WR-CALNAME:$this->name$LINE_BREAK";
            $this->file .= "CALSCALE:$this->scale$LINE_BREAK";
            $this->file .= "METHOD:$this->method$LINE_BREAK";
            
            foreach ($this->events as $k => $events) {

                $this->file .= "BEGIN:VEVENT$LINE_BREAK";

                foreach ($events as $KEY => $VALUE) { 
                    if (!empty($VALUE)) {
                        $KEY = substr($KEY, 5);
                        $this->file .= "$KEY:$VALUE$LINE_BREAK";
                    }
                }

                $this->file .= "END:VEVENT$LINE_BREAK";
                
            }

            $this->file .= "END:VCALENDAR";

            if ($dir === null) {

                echo $this->file;

            } else if ($dir === true) {

                header("Content-type:text/calendar");
                header('Content-Disposition: attachment; filename="'.$this->name.'.ics"');
                Header('Content-Length: '.strlen($this->file));
                Header('Connection: close');

                echo $this->file;

            } else {

                $file = fopen($dir, "w");
                fwrite($file, $this->file);
                fclose($file);

            }

        }

    }