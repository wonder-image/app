<?php

    function translateDate($date, $format) {

        if ($format == 'day') {

            $newDate = date("D", strtotime($date));

            if ($newDate == "Mon") {
                $newDate = "Lunedì";
            }elseif ($newDate == "Tue") {
                $newDate = "Martedì";
            }elseif ($newDate == "Wed") {
                $newDate = "Mercoledì";
            }elseif ($newDate == "Thu") {
                $newDate = "Giovedì";
            }elseif ($newDate == "Fri") {
                $newDate = "Venerdì";
            }elseif ($newDate == "Sat") {
                $newDate = "Sabato";
            }elseif ($newDate == "Sun") {
                $newDate = "Domenica";
            }

        }

        if ($format == 'month') {

            $newDate = date("F", strtotime($date));

            if ($newDate == "January") {
                $newDate = "Gennaio";
            }elseif ($newDate == "February") {
                $newDate = "Febbraio";
            }elseif ($newDate == "March") {
                $newDate = "Marzo";
            }elseif ($newDate == "April") {
                $newDate = "Aprile";
            }elseif ($newDate == "May") {
                $newDate = "Maggio";
            }elseif ($newDate == "June") {
                $newDate = "Giugno";
            }elseif ($newDate == "July") {
                $newDate = "Luglio";
            }elseif ($newDate == "August") {
                $newDate = "Agosto";
            }elseif ($newDate == "September") {
                $newDate = "Settembre";
            }elseif ($newDate == "October") {
                $newDate = "Ottobre";
            }elseif ($newDate == "November") {
                $newDate = "Novembre";
            }elseif ($newDate == "December") {
                $newDate = "Dicembre";
            }

        }

        return $newDate;

    }

    function labelDate($day) {

        $from = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$day,date('Y')));
        $to = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-1,date('Y')));

        $dates = array();
        
        if ($day >= 2) {

            array_push($dates, $from);

            $date = $from;

            $found = false;

            for($i = 1; $i > 0 && !$found; $i++){

                list($year,$month,$day) = explode("-", $date);
                
                $date = date("Y-m-d",mktime(0,0,0,$month,$day+1,$year));

                if($date == $to){
                    $found = true;
                }else{
                    array_push($dates, $date);
                }

            }

            array_push($dates, $to);

        }

        if ($day == 1) {
            array_push($dates, $to);
        }

        if ($day == 0) {
            array_push($dates, $from);
        }

        return $dates;

    }

    function arrayDay($from, $to) {

        list($day,$month,$year) = explode("/", $from);
        $from = "$year-$month-$day";
        list($day,$month,$year) = explode("/", $to);
        $to = "$year-$month-$day";

        $dates = array();

        array_push($dates, $from);

        $date = $from;

        $found = false;

        for($i = 1; $i > 0 && !$found; $i++){

            list($year,$month,$day) = explode("-", $date);
            
            $date = date("Y-m-d",mktime(0,0,0,$month,$day+1,$year));

            if($date == $to){
                $found = true;
            }else{
                array_push($dates, $date);
            }

        }

        array_push($dates, $to);

        return $dates;

    }

    function calcYear($initialDate, $finalDate = null) {

        $initialDate = str_replace('/', '-', $initialDate);
        $initialDate = date('Y-m-d', strtotime($initialDate));

        if ($finalDate == null) { 
            $finalDate = date('Y-m-d');
        } else {
            $finalDate = date('Y-m-d', strtotime($finalDate));
        }

        $diff = date_diff(date_create($initialDate), date_create($finalDate));
        $year = $diff->format('%y');

        return $year;

    }

?>